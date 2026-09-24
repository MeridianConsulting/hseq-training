"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  apiGet,
  apiPost,
  clearStoredToken,
  getStoredToken,
  getTokenExpiresAt,
  onSesionExpirada,
  setStoredToken,
} from "@/lib/api";
import type { LoginResponse, UsuarioSesion } from "@/lib/tipos";

type AuthContextValue = {
  usuario: UsuarioSesion | null;
  listo: boolean;
  autenticado: boolean;
  /** Autorizacion de la interfaz. El backend vuelve a validar cada permiso. */
  puede: (permiso: string) => boolean;
  login: (usuario: string, password: string) => Promise<string | null>;
  logout: () => Promise<void>;
  cerrarSesionLocal: () => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

/** Renueva cuando queda ~25 % de vida o 5 minutos, lo que sea menor (mín. 30 s). */
function msHastaRefresh(expiraAt: number): number {
  const restante = expiraAt - Date.now();
  if (restante <= 0) {
    return 0;
  }
  const margen = Math.min(5 * 60 * 1000, Math.max(30_000, restante * 0.25));
  return Math.max(5_000, restante - margen);
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [usuario, setUsuario] = useState<UsuarioSesion | null>(null);
  const [listo, setListo] = useState(false);
  const renovandoRef = useRef(false);

  const cerrarSesionLocal = useCallback(() => {
    clearStoredToken();
    setUsuario(null);
  }, []);

  const aplicarLogin = useCallback((data: LoginResponse) => {
    setStoredToken(data.token, data.expires_in);
    setUsuario(data.usuario);
  }, []);

  const renovarSesion = useCallback(async (): Promise<boolean> => {
    if (renovandoRef.current || !getStoredToken()) {
      return false;
    }
    renovandoRef.current = true;
    try {
      const respuesta = await apiPost<LoginResponse>("/api/auth/refresh", {});
      if (!respuesta.success || !respuesta.data) {
        return false;
      }
      aplicarLogin(respuesta.data);
      return true;
    } finally {
      renovandoRef.current = false;
    }
  }, [aplicarLogin]);

  useEffect(() => {
    let cancelado = false;

    const restaurarSesion = async () => {
      const token = getStoredToken();
      if (token) {
        // Asegura marca de expiración si solo había token (sesiones previas).
        setStoredToken(token);

        const respuesta = await apiGet<UsuarioSesion>("/api/auth/me");

        if (cancelado) {
          return;
        }

        if (respuesta.success && respuesta.data) {
          setUsuario(respuesta.data);
        } else {
          clearStoredToken();
          setUsuario(null);
        }
      }

      if (!cancelado) {
        setListo(true);
      }
    };

    void restaurarSesion();

    return () => {
      cancelado = true;
    };
  }, []);

  // Renovación silenciosa antes de que caduque el token.
  useEffect(() => {
    if (!usuario) {
      return;
    }

    let timer: ReturnType<typeof setTimeout> | undefined;
    let cancelado = false;

    const programar = () => {
      if (cancelado) {
        return;
      }
      const expiraAt = getTokenExpiresAt();
      if (expiraAt == null) {
        return;
      }
      const espera = msHastaRefresh(expiraAt);
      timer = setTimeout(() => {
        void (async () => {
          const ok = await renovarSesion();
          if (!cancelado && ok) {
            programar();
          }
        })();
      }, espera);
    };

    programar();

    const alVisible = () => {
      if (document.visibilityState !== "visible" || cancelado) {
        return;
      }
      const expiraAt = getTokenExpiresAt();
      if (expiraAt == null) {
        return;
      }
      // Si queda poco o ya pasó el momento de refresh, renovar ya.
      if (msHastaRefresh(expiraAt) <= 15_000) {
        void (async () => {
          const ok = await renovarSesion();
          if (!cancelado && ok) {
            if (timer) {
              clearTimeout(timer);
            }
            programar();
          }
        })();
      }
    };
    document.addEventListener("visibilitychange", alVisible);

    return () => {
      cancelado = true;
      if (timer) {
        clearTimeout(timer);
      }
      document.removeEventListener("visibilitychange", alVisible);
    };
  }, [usuario, renovarSesion]);

  // Cualquier 401 posterior (token expirado o revocado) cierra la sesion local.
  useEffect(() => onSesionExpirada(cerrarSesionLocal), [cerrarSesionLocal]);

  const login = useCallback(
    async (identificador: string, password: string) => {
      const respuesta = await apiPost<LoginResponse>("/api/auth/login", {
        usuario: identificador,
        password,
      });

      if (!respuesta.success || !respuesta.data) {
        return respuesta.message || "No fue posible iniciar sesión.";
      }

      aplicarLogin(respuesta.data);

      return null;
    },
    [aplicarLogin],
  );

  const logout = useCallback(async () => {
    try {
      await apiPost("/api/auth/logout");
    } finally {
      cerrarSesionLocal();
    }
  }, [cerrarSesionLocal]);

  const puede = useCallback(
    (permiso: string) => usuario?.permisos?.includes(permiso) ?? false,
    [usuario],
  );

  const value = useMemo(
    () => ({
      usuario,
      listo,
      autenticado: usuario !== null,
      puede,
      login,
      logout,
      cerrarSesionLocal,
    }),
    [usuario, listo, puede, login, logout, cerrarSesionLocal],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const contexto = useContext(AuthContext);

  if (!contexto) {
    throw new Error("useAuth debe usarse dentro de AuthProvider");
  }

  return contexto;
}
