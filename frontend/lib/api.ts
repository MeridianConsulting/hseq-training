export type ApiErrorMap = Record<string, string[]>;

export type ApiResponse<T> = {
  success: boolean;
  message: string;
  data: T | null;
  errors: ApiErrorMap | null;
};

export type Paginacion = {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
};

export type ListaPaginada<T> = {
  items: T[];
  pagination: Paginacion;
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
  const raw = (process.env.NEXT_PUBLIC_API_URL ?? "").trim();
  return raw.replace(/\/$/, "");
}

export function withQuery(
  path: string,
  params: Record<string, string | number | boolean | undefined | null> = {},
): string {
  const query = new URLSearchParams();

  for (const [clave, valor] of Object.entries(params)) {
    if (valor === undefined || valor === null || valor === "") {
      continue;
    }
    query.set(clave, String(valor));
  }

  const cadena = query.toString();
  return cadena ? `${path}?${cadena}` : path;
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

  if (
    respuesta.status === 401
    && !path.startsWith("/api/auth/login")
    && !path.startsWith("/api/ping")
  ) {
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

export function apiPut<T>(path: string, body?: unknown): Promise<ApiResponse<T>> {
  return apiFetch<T>(path, {
    method: "PUT",
    body: body === undefined ? undefined : JSON.stringify(body),
  });
}

export function apiDelete<T>(path: string): Promise<ApiResponse<T>> {
  return apiFetch<T>(path, { method: "DELETE" });
}

export async function apiPostForm<T>(path: string, form: FormData): Promise<ApiResponse<T>> {
  const token = getStoredToken();
  const headers = new Headers();
  headers.set("Accept", "application/json");

  if (token && !path.startsWith("/api/auth/login")) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const respuesta = await fetch(`${apiBase()}${path}`, {
    method: "POST",
    headers,
    body: form,
  });

  if (respuesta.status === 401 && !path.startsWith("/api/auth/login")) {
    clearStoredToken();
    notificarSesionExpirada();
  }

  const payload = (await respuesta.json()) as ApiResponse<T>;

  return payload;
}

export async function apiDownload(path: string, nombreFallback: string): Promise<void> {
  const token = getStoredToken();
  const headers = new Headers();
  headers.set("Accept", "*/*");

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const respuesta = await fetch(`${apiBase()}${path}`, { headers });

  if (respuesta.status === 401) {
    clearStoredToken();
    notificarSesionExpirada();
    throw new Error("Sesión expirada");
  }

  if (!respuesta.ok) {
    let mensaje = "No fue posible descargar el archivo.";
    try {
      const payload = (await respuesta.json()) as ApiResponse<unknown>;
      if (payload.message) {
        mensaje = payload.message;
      }
    } catch {
      // El cuerpo no es JSON.
    }
    throw new Error(mensaje);
  }

  const blob = await respuesta.blob();
  const url = URL.createObjectURL(blob);
  const enlace = document.createElement("a");
  enlace.href = url;
  enlace.download = nombreFallback;
  document.body.appendChild(enlace);
  enlace.click();
  enlace.remove();
  URL.revokeObjectURL(url);
}
