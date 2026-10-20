"use client";

import { useState, type FormEvent } from "react";
import {
  ListaEvidencias,
  VistaPreviaArchivo,
  subirSoportes,
  useVistaPrevia,
} from "@/app/(app)/cumplimientos/evidencias";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Modal } from "@/components/ui/modal";
import { apiFetch } from "@/lib/api";
import type { Cumplimiento, SoporteCumplimiento } from "@/lib/tipos";

/**
 * Edición de un cumplimiento del historial: fecha, nota y soportes.
 * Todos los campos son obligatorios (al menos un soporte entre los actuales y los nuevos).
 */
export function EditarHistorial({
  item,
  onCerrar,
  onGuardado,
}: {
  item: Cumplimiento;
  onCerrar: () => void;
  onGuardado: (mensaje: string) => void | Promise<void>;
}) {
  const [fecha, setFecha] = useState((item.fecha_realizacion ?? "").slice(0, 10));
  const [nota, setNota] = useState(item.nota_evaluacion != null ? String(item.nota_evaluacion) : "");
  const [soportes, setSoportes] = useState<SoporteCumplimiento[]>(item.soportes ?? []);
  const [archivos, setArchivos] = useState<File[]>([]);
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const vista = useVistaPrevia();
  const hoy = new Date().toISOString().slice(0, 10);

  async function enviar(e: FormEvent) {
    e.preventDefault();
    setError(null);
    const faltantes: string[] = [];
    if (!fecha) faltantes.push("la fecha real de ejecución");
    if (nota.trim() === "") faltantes.push("la nota");
    if (soportes.length + archivos.length === 0) faltantes.push("al menos un soporte");
    if (faltantes.length > 0) {
      setError(`Todos los campos son obligatorios. Falta: ${faltantes.join(", ")}.`);
      return;
    }

    setEnviando(true);
    const r = await apiFetch<Cumplimiento>(`/api/cumplimientos/${item.cumplimiento_id}`, {
      method: "PUT",
      body: JSON.stringify({
        fecha_realizacion: fecha,
        nota_evaluacion: Number(nota.replace(",", ".")),
        horas_efectivas: item.horas_efectivas ?? 1,
        observaciones: item.observaciones ?? null,
      }),
    });
    if (r.cancelada) {
      setEnviando(false);
      return;
    }
    if (!r.success) {
      setEnviando(false);
      setError(r.message || "No fue posible actualizar el historial.");
      return;
    }
    const errArchivos = archivos.length > 0 ? await subirSoportes(item.cumplimiento_id, archivos) : null;
    setEnviando(false);
    if (errArchivos) {
      setError(`Los datos se actualizaron, pero un soporte no se cargó: ${errArchivos}`);
      return;
    }
    await onGuardado(r.message || "Historial actualizado.");
  }

  return (
    <Modal
      abierto
      titulo={`Editar historial · ${item.capacitacion_codigo ?? ""} — ${item.capacitacion_nombre ?? ""}`}
      onCerrar={onCerrar}
    >
      <form onSubmit={(ev) => void enviar(ev)} className="space-y-4">
        {error ? <Alert tono="error">{error}</Alert> : null}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field etiqueta="Fecha real de ejecución">
            <input
              className={inputClass}
              type="date"
              max={hoy}
              value={fecha}
              onChange={(e) => setFecha(e.target.value)}
              required
            />
          </Field>
          <Field etiqueta="Nota">
            <input
              className={inputClass}
              type="number"
              min={0}
              max={5}
              step="0.01"
              value={nota}
              onChange={(e) => setNota(e.target.value)}
              required
            />
          </Field>
        </div>

        <div>
          <p className="mb-1 text-xs uppercase text-slate-500">Soportes actuales</p>
          <ListaEvidencias
            soportes={soportes}
            puedeEliminar={soportes.length + archivos.length > 1}
            onEliminado={(sid) => setSoportes((prev) => prev.filter((s) => s.soporte_id !== sid))}
            onError={setError}
          />
        </div>

        <Field etiqueta="Agregar soportes (PDF, JPG, PNG)">
          <input
            className={inputClass}
            type="file"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
            onChange={(e) => setArchivos(Array.from(e.target.files ?? []))}
            required={soportes.length === 0}
          />
          {archivos.length > 0 ? (
            <ul className="mt-1 space-y-0.5 text-xs">
              {archivos.map((a, i) => (
                <li key={`${a.name}-${i}`} className="flex items-center gap-2">
                  <span className="truncate" title={a.name}>{a.name}</span>
                  <button
                    type="button"
                    className="font-medium text-hseq-700 underline"
                    onClick={() => vista.abrir(a.name, a)}
                  >
                    Ver
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
        </Field>

        <div className="flex justify-end gap-2">
          <Button type="button" variante="secondary" onClick={onCerrar}>
            Cancelar
          </Button>
          <Button type="submit" disabled={enviando}>
            {enviando ? "Guardando…" : "Guardar cambios"}
          </Button>
        </div>
        <VistaPreviaArchivo previa={vista.previa} onCerrar={vista.cerrar} />
      </form>
    </Modal>
  );
}
