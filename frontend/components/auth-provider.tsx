"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import {
  apiGet,
  apiPost,
  clearStoredToken,
  getStoredToken,
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

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [usuario, setUsuario] = useState<UsuarioSesion | null>(null);
  const [listo, setListo] = useState(false);

  const cerrarSesionLocal = useCallback(() => {
    clearStoredToken();
    setUsuario(null);
  }, []);

  useEffect(() => {
    let cancelado = false;

    const restaurarSesion = async () => {
      if (getStoredToken()) {
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

  // Cualquier 401 posterior (token expirado o revocado) cierra la sesion local.
  useEffect(() => onSesionExpirada(cerrarSesionLocal), [cerrarSesionLocal]);

  const login = useCallback(async (identificador: string, password: string) => {
    const respuesta = await apiPost<LoginResponse>("/api/auth/login", {
      usuario: identificador,
      password,
    });

    if (!respuesta.success || !respuesta.data) {
      return respuesta.message || "No fue posible iniciar sesión.";
    }

    setStoredToken(respuesta.data.token);
    setUsuario(respuesta.data.usuario);

    return null;
  }, []);

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
