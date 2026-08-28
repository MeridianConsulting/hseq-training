/**
 * Tipos que reflejan las respuestas de la API.
 */

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
