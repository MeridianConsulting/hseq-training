"use client";

import { useState, type FormEvent } from "react";
import { VistaPreviaArchivo, useVistaPrevia } from "@/app/(app)/cumplimientos/evidencias";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { apiPostForm } from "@/lib/api";
import type { ConsultaCumplimiento } from "@/lib/tipos";

/** Estados de una asignación que aún no tiene cumplimiento aprobado. */
export function puedeRegistrarDirecto(item: ConsultaCumplimiento): boolean {
  return item.estado_calculado.startsWith("PENDIENTE");
}

/**
 * Registro directo de cumplimiento (sin sesión): fecha real, nota y soportes.
 * Si un archivo falla, el backend no deja nada registrado.
 */
export function RegistroDirecto({
  persona,
  onVolver,
  onGuardado,
}: {
  persona: ConsultaCumplimiento;
  onVolver: () => void;
  onGuardado: (mensaje: string) => void | Promise<void>;
}) {
  const [fecha, setFecha] = useState("");
  const [nota, setNota] = useState("");
  const [archivos, setArchivos] = useState<File[]>([]);
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const vista = useVistaPrevia();
  const hoy = new Date().toISOString().slice(0, 10);

  async function enviar(e: FormEvent) {
    e.preventDefault();
    setError(null);
    const faltantes: string[] = [];
    if (!fecha) faltantes.push("la fecha en que la realizó");
    if (nota.trim() === "") faltantes.push("la nota");
    if (persona.soportes_count === 0 && archivos.length === 0) faltantes.push("al menos un soporte");
    if (faltantes.length > 0) {
      setError(`Todos los campos son obligatorios. Falta: ${faltantes.join(", ")}.`);
      return;
    }
    const form = new FormData();
    form.append("asignacion_id", String(persona.asignacion_id));
    form.append("fecha_realizacion", fecha);
    form.append("nota_evaluacion", nota.replace(",", "."));
    for (const archivo of archivos) {
      form.append("archivos[]", archivo);
    }
    setEnviando(true);
    const r = await apiPostForm("/api/cumplimientos/directo", form);
    setEnviando(false);
    if (r.cancelada) {
      return;
    }
    if (!r.success) {
      setError(r.message || "No fue posible registrar el cumplimiento.");
      return;
    }
    await onGuardado(r.message || "Cumplimiento registrado.");
  }

  return (
    <form onSubmit={(ev) => void enviar(ev)} className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold text-hseq-900">
            Registrar cumplimiento · {persona.persona_nombre ?? `Persona ${persona.persona_id_ext}`}
          </h3>
          <p className="text-sm text-slate-600">
            {persona.numero_documento ?? "—"} · {persona.cargo ?? "—"}
          </p>
        </div>
        <Button type="button" variante="secondary" onClick={onVolver}>
          Volver al listado
        </Button>
      </div>

      {error ? <Alert tono="error">{error}</Alert> : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <Field etiqueta="Fecha en que la realizó">
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
        <Field etiqueta="Soportes (PDF, JPG, PNG)">
          <input
            className={inputClass}
            type="file"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
            onChange={(e) => setArchivos(Array.from(e.target.files ?? []))}
            required={persona.soportes_count === 0}
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
      </div>

      <div className="flex justify-end">
        <Button type="submit" disabled={enviando}>
          {enviando ? "Guardando…" : "Registrar cumplimiento"}
        </Button>
      </div>
      <VistaPreviaArchivo previa={vista.previa} onCerrar={vista.cerrar} />
    </form>
  );
}
