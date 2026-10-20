"use client";

import { useEffect, useState } from "react";
import { Modal } from "@/components/ui/modal";
import { apiBlob, apiDelete, apiDownload, apiPostForm } from "@/lib/api";
import type { SoporteCumplimiento } from "@/lib/tipos";

const MENSAJE_SIN_ARCHIVO =
  "Debe adjuntar al menos un archivo antes de completar este cumplimiento.";

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
    if (r.cancelada) {
      return null;
    }
    if (!r.success) {
      return r.message || "No fue posible cargar el archivo.";
    }
  }
  return null;
}

type Previa = { nombre: string; url: string; mime: string };

function esPrevisualizable(mime: string): boolean {
  return mime === "application/pdf" || mime.startsWith("image/");
}

/** Modal que muestra un PDF o imagen a partir de una URL de objeto (blob:). */
export function VistaPreviaArchivo({ previa, onCerrar }: { previa: Previa | null; onCerrar: () => void }) {
  return (
    <Modal abierto={previa !== null} titulo={previa?.nombre ?? ""} onCerrar={onCerrar} amplio>
      {previa === null ? null : previa.mime.startsWith("image/") ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={previa.url} alt={previa.nombre} className="mx-auto max-h-[75dvh] object-contain" />
      ) : esPrevisualizable(previa.mime) ? (
        <iframe src={previa.url} title={previa.nombre} className="h-[75dvh] w-full rounded-md border border-slate-200" />
      ) : (
        <p className="text-sm text-slate-600">Este tipo de archivo no tiene vista previa. Descárguelo para verlo.</p>
      )}
    </Modal>
  );
}

/** Estado de vista previa: libera la URL de objeto al cerrar o desmontar. */
export function useVistaPrevia() {
  const [previa, setPrevia] = useState<Previa | null>(null);

  useEffect(() => {
    return () => {
      if (previa) URL.revokeObjectURL(previa.url);
    };
  }, [previa]);

  return {
    previa,
    abrir: (nombre: string, blob: Blob, mime?: string) =>
      setPrevia({ nombre, url: URL.createObjectURL(blob), mime: mime || blob.type }),
    cerrar: () => setPrevia(null),
  };
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
  const vista = useVistaPrevia();

  if (soportes.length === 0) {
    return <span className="text-slate-400">—</span>;
  }

  async function ver(item: SoporteCumplimiento) {
    try {
      const blob = await apiBlob(`/api/cumplimientos/soportes/${item.soporte_id}/archivo`);
      vista.abrir(item.nombre_archivo || "Evidencia", blob, item.mime_type || blob.type);
    } catch (e) {
      onError?.(e instanceof Error ? e.message : "No fue posible abrir el archivo.");
    }
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
    if (r.cancelada) {
      return;
    }
    if (!r.success) {
      onError?.(r.message || "No fue posible eliminar el archivo.");
      return;
    }
    onEliminado?.(item.soporte_id);
  }

  return (
    <>
    <ul className="space-y-1">
      {soportes.map((item) => (
        <li key={item.soporte_id} className="flex flex-wrap items-center gap-2 text-sm">
          <span className="truncate" title={item.nombre_archivo}>
            {item.nombre_archivo}
          </span>
          {esPrevisualizable(item.mime_type ?? "") ? (
            <button type="button" className="font-medium text-hseq-700 underline" onClick={() => void ver(item)}>
              Ver
            </button>
          ) : null}
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
    <VistaPreviaArchivo previa={vista.previa} onCerrar={vista.cerrar} />
    </>
  );
}

export { MENSAJE_SIN_ARCHIVO };
