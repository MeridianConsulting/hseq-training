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
  apiFetch,
  clearStoredToken,
  getStoredToken,
  setStoredToken,
  type LoginResponse,
  type UsuarioSesion,
} from "@/lib/api";

type AuthContextValue = {
  usuario: UsuarioSesion | null;
  listo: boolean;
  login: (usuario: string, password: string) => Promise<string | null>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [usuario, setUsuario] = useState<UsuarioSesion | null>(null);
  const [listo, setListo] = useState(false);

  useEffect(() => {
    const token = getStoredToken();

    if (!token) {
      setListo(true);
      return;
    }

    let cancelado = false;

    apiFetch<UsuarioSesion>("/api/auth/me")
      .then((respuesta) => {
        if (cancelado) {
          return;
        }

        if (respuesta.success && respuesta.data) {
          setUsuario(respuesta.data);
        } else {
          clearStoredToken();
          setUsuario(null);
        }
      })
      .catch(() => {
        if (!cancelado) {
          clearStoredToken();
          setUsuario(null);
        }
      })
      .finally(() => {
        if (!cancelado) {
          setListo(true);
        }
      });

    return () => {
      cancelado = true;
    };
  }, []);

  const login = useCallback(async (identificador: string, password: string) => {
    try {
      const respuesta = await apiFetch<LoginResponse>("/api/auth/login", {
        method: "POST",
        body: JSON.stringify({
          usuario: identificador,
          password,
        }),
      });

      if (!respuesta.success || !respuesta.data) {
        return respuesta.message || "No fue posible iniciar sesión";
      }

      setStoredToken(respuesta.data.token);
      setUsuario(respuesta.data.usuario);
      return null;
    } catch {
      return "No se pudo conectar con el servidor. Verifique que la API esté en línea.";
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await apiFetch("/api/auth/logout", { method: "POST" });
    } catch {
      // El cierre local aplica aunque el servidor no responda.
    }

    clearStoredToken();
    setUsuario(null);
  }, []);

  const value = useMemo(
    () => ({
      usuario,
      listo,
      login,
      logout,
    }),
    [usuario, listo, login, logout],
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
