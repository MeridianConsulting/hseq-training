"use client";

import { apiDelete, apiDownload, apiPostForm } from "@/lib/api";
import type { SoporteCumplimiento } from "@/lib/tipos";

const MENSAJE_SIN_ARCHIVO =
  "Debe adjuntar al menos un archivo para completar este cumplimiento.";

export async function subirSoportes(
  cumplimientoId: number,
  archivos: File[],
): Promise<string | null> {
  for (const archivo of archivos) {
    const form = new FormData();
    form.append("archivo", archivo);
    form.append("tipo_soporte", "CERTIFICADO");
    const r = await apiPostForm<SoporteCumplimiento>(
      `/api/cumplimientos/${cumplimientoId}/soportes`,
      form,
    );
    if (!r.success) {
      return r.message || "No fue posible cargar el archivo.";
    }
  }
  return null;
}

export function ListaEvidencias({
  soportes,
  puedeEliminar = false,
  onEliminado,
  onError,
}: {
  soportes: SoporteCumplimiento[];
  puedeEliminar?: boolean;
  onEliminado?: (soporteId: number) => void;
  onError?: (mensaje: string) => void;
}) {
  if (soportes.length === 0) {
    return <span className="text-slate-400">—</span>;
  }

  async function descargar(item: SoporteCumplimiento) {
    try {
      await apiDownload(
        `/api/cumplimientos/soportes/${item.soporte_id}/archivo`,
        item.nombre_archivo || "evidencia",
      );
    } catch (e) {
      onError?.(e instanceof Error ? e.message : "No fue posible descargar el archivo.");
    }
  }

  async function eliminar(item: SoporteCumplimiento) {
    if (!window.confirm(`¿Eliminar ${item.nombre_archivo}?`)) {
      return;
    }
    const r = await apiDelete(`/api/cumplimientos/soportes/${item.soporte_id}`);
    if (!r.success) {
      onError?.(r.message || "No fue posible eliminar el archivo.");
      return;
    }
    onEliminado?.(item.soporte_id);
  }

  return (
    <ul className="space-y-1">
      {soportes.map((item) => (
        <li key={item.soporte_id} className="flex flex-wrap items-center gap-2 text-sm">
          <span className="truncate" title={item.nombre_archivo}>
            {item.nombre_archivo}
          </span>
          <button type="button" className="font-medium text-hseq-700 underline" onClick={() => void descargar(item)}>
            Descargar
          </button>
          {puedeEliminar ? (
            <button type="button" className="text-red-700 underline" onClick={() => void eliminar(item)}>
              Eliminar
            </button>
          ) : null}
        </li>
      ))}
    </ul>
  );
}

export { MENSAJE_SIN_ARCHIVO };
