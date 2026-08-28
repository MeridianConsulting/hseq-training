export type ApiErrorMap = Record<string, string[]>;

export type ApiResponse<T> = {
  success: boolean;
  message: string;
  data: T | null;
  errors: ApiErrorMap | null;
};

export type RolHseq = {
  role_id: number;
  nombre: string;
};

export type UsuarioSesion = {
  usuario_id: number;
  nombre_usuario: string;
  correo: string;
  rol: string;
  estado: string;
  ultimo_acceso: string | null;
  roles: RolHseq[];
  permisos: string[];
};

export type LoginResponse = {
  token: string;
  token_type: string;
  expires_in: number;
  usuario: UsuarioSesion;
};

const TOKEN_KEY = "hseq_token";
const SESION_EXPIRADA = "hseq:sesion-expirada";

export function getStoredToken(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return localStorage.getItem(TOKEN_KEY);
}

export function setStoredToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearStoredToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

export function onSesionExpirada(callback: () => void): () => void {
  if (typeof window === "undefined") {
    return () => undefined;
  }

  const handler = () => callback();
  window.addEventListener(SESION_EXPIRADA, handler);

  return () => window.removeEventListener(SESION_EXPIRADA, handler);
}

function notificarSesionExpirada(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.dispatchEvent(new Event(SESION_EXPIRADA));
}

function apiBase(): string {
  const raw = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost/hseq-training/backend/public";
  return raw.replace(/\/$/, "");
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<ApiResponse<T>> {
  const token = getStoredToken();
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  if (!headers.has("Content-Type") && options.body) {
    headers.set("Content-Type", "application/json");
  }

  if (token && !path.startsWith("/api/auth/login")) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const respuesta = await fetch(`${apiBase()}${path}`, {
    ...options,
    headers,
  });

  if (respuesta.status === 401 && !path.startsWith("/api/auth/login")) {
    clearStoredToken();
    notificarSesionExpirada();
  }

  const payload = (await respuesta.json()) as ApiResponse<T>;

  return payload;
}

export function apiGet<T>(path: string): Promise<ApiResponse<T>> {
  return apiFetch<T>(path, { method: "GET" });
}

export function apiPost<T>(path: string, body?: unknown): Promise<ApiResponse<T>> {
  return apiFetch<T>(path, {
    method: "POST",
    body: body === undefined ? undefined : JSON.stringify(body),
  });
}
