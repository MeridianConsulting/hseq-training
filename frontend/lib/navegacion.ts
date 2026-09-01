/**
 * Menu de la aplicacion.
 *
 * Cada entrada declara el permiso que exige el backend para la ruta. Ocultar la
 * entrada es solo comodidad: la autorizacion real la resuelve PermisoMiddleware.
 */

export type EntradaMenu = {
  ruta: string;
  etiqueta: string;
  permiso: string;
  /** Sin endpoints todavia: se muestra como modulo en preparacion. */
  preparado?: boolean;
};

export type GrupoMenu = {
  titulo: string;
  entradas: EntradaMenu[];
};

export const MENU: GrupoMenu[] = [
  {
    titulo: "Seguimiento",
    entradas: [
      { ruta: "/dashboard", etiqueta: "Panel de control", permiso: "dashboard.ver" },
      { ruta: "/alertas", etiqueta: "Alertas", permiso: "alertas.ver" },
      { ruta: "/reportes", etiqueta: "Reportes", permiso: "reportes.ver" },
    ],
  },
  {
    titulo: "Programa",
    entradas: [
      { ruta: "/capacitaciones", etiqueta: "Capacitaciones", permiso: "capacitaciones.ver" },
      { ruta: "/matriz", etiqueta: "Matriz de aplicabilidad", permiso: "matriz.ver" },
      { ruta: "/plan-anual", etiqueta: "Plan anual", permiso: "planes.ver" },
      { ruta: "/cronograma", etiqueta: "Tablero de Cronograma", permiso: "planes.ver" },
      { ruta: "/sesiones", etiqueta: "Sesiones y asistencia", permiso: "sesiones.ver" },
    ],
  },
  {
    titulo: "Personas",
    entradas: [
      { ruta: "/personal", etiqueta: "Personal corporativo", permiso: "personal.ver" },
      { ruta: "/asignaciones", etiqueta: "Asignaciones", permiso: "asignaciones.ver" },
      { ruta: "/cumplimientos", etiqueta: "Cumplimientos", permiso: "cumplimientos.ver" },
    ],
  },
  {
    titulo: "Administración",
    entradas: [
      { ruta: "/configuracion", etiqueta: "Catálogos", permiso: "catalogos.ver" },
      { ruta: "/auditoria", etiqueta: "Auditoría", permiso: "auditoria.ver" },
      { ruta: "/migracion", etiqueta: "Carga inicial Excel", permiso: "migracion.ejecutar" },
    ],
  },
];

/** Primera ruta a la que el usuario puede entrar segun sus permisos. */
export function rutaInicial(permisos: string[]): string {
  for (const grupo of MENU) {
    for (const entrada of grupo.entradas) {
      if (!entrada.preparado && permisos.includes(entrada.permiso)) {
        return entrada.ruta;
      }
    }
  }

  return "/sin-acceso";
}
