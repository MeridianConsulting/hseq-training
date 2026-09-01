"use client";

import { FormEvent, useEffect, useState } from "react";
import { ListaEvidencias, MENSAJE_SIN_ARCHIVO } from "@/app/(app)/cumplimientos/evidencias";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { apiGet, withQuery } from "@/lib/api";
import type { PreviewCumplimiento, SoporteCumplimiento } from "@/lib/tipos";

export type DatosCumplimiento = {
  fecha_realizacion: string;
  resultado: string;
  horas_efectivas: string;
  observaciones: string;
  nota_evaluacion: string;
  archivos: File[];
};

export function vacioCumplimiento(fechaDefault = ""): DatosCumplimiento {
  return {
    fecha_realizacion: fechaDefault,
    resultado: "APROBADO",
    horas_efectivas: "",
    observaciones: "",
    nota_evaluacion: "",
    archivos: [],
  };
}

function formatoFecha(valor: string | null): string {
  if (!valor) return "Sin vencimiento";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

export function FormularioCumplimiento({
  asignacionId,
  sesionId,
  cumplimientoId,
  soportes = [],
  fechaDefault,
  onSubmit,
  onCancelar,
  onSoporteEliminado,
  onError,
  enviando = false,
  modoEdicion = false,
}: {
  asignacionId: number;
  sesionId: number;
  cumplimientoId?: number | null;
  soportes?: SoporteCumplimiento[];
  fechaDefault?: string;
  onSubmit: (evento: FormEvent, datos: DatosCumplimiento) => void;
  onCancelar: () => void;
  onSoporteEliminado?: (soporteId: number) => void;
  onError?: (mensaje: string) => void;
  enviando?: boolean;
  modoEdicion?: boolean;
}) {
  const [datos, setDatos] = useState<DatosCumplimiento>(vacioCumplimiento(fechaDefault ?? ""));
  const [preview, setPreview] = useState<PreviewCumplimiento | null>(null);
  const [cargados, setCargados] = useState<SoporteCumplimiento[]>(soportes);
  const [avisoLocal, setAvisoLocal] = useState<string | null>(null);

  useEffect(() => {
    if (!cumplimientoId) {
      setCargados([]);
      return;
    }
    void (async () => {
      const r = await apiGet<SoporteCumplimiento[]>(`/api/cumplimientos/${cumplimientoId}/soportes`);
      if (r.success && Array.isArray(r.data)) {
        setCargados(r.data);
      }
    })();
  }, [cumplimientoId]);

  useEffect(() => {
    if (sesionId < 1 || asignacionId < 1 || !datos.fecha_realizacion) {
      setPreview(null);
      return;
    }
    const id = window.setTimeout(() => {
      void (async () => {
        const r = await apiGet<PreviewCumplimiento>(
          withQuery("/api/cumplimientos/previsualizar", {
            sesion_id: sesionId,
            asignacion_ids: asignacionId,
            fecha_realizacion: datos.fecha_realizacion,
          }),
        );
        setPreview(r.success && r.data ? r.data : null);
      })();
    }, 250);
    return () => window.clearTimeout(id);
  }, [sesionId, asignacionId, datos.fecha_realizacion]);

  const item = preview?.items[0];
  const requiere = Boolean(item?.requiere_certificado);
  const requiereEval = Boolean(item?.requiere_evaluacion);
  const minima = item?.nota_minima ?? 0;
  const countExistentes = cargados.length || item?.soportes_count || 0;
  const notaNumero = datos.nota_evaluacion.trim() === "" ? null : Number(datos.nota_evaluacion);
  const evalAprobada =
    requiereEval && notaNumero !== null && !Number.isNaN(notaNumero) ? notaNumero >= minima : null;

  useEffect(() => {
    if (item?.nota_evaluacion == null || datos.nota_evaluacion !== "") {
      return;
    }
    setDatos((prev) => ({ ...prev, nota_evaluacion: String(item.nota_evaluacion) }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [item?.nota_evaluacion]);

  function enviar(evento: FormEvent) {
    setAvisoLocal(null);
    if (requiere && countExistentes + datos.archivos.length === 0) {
      evento.preventDefault();
      setAvisoLocal(MENSAJE_SIN_ARCHIVO);
      return;
    }
    if (requiereEval) {
      const texto = datos.nota_evaluacion.trim();
      if (texto === "") {
        evento.preventDefault();
        setAvisoLocal("La nota es obligatoria.");
        return;
      }
      const n = Number(texto);
      if (Number.isNaN(n)) {
        evento.preventDefault();
        setAvisoLocal("La nota debe ser numérica.");
        return;
      }
      if (n < 0 || n > 5) {
        evento.preventDefault();
        setAvisoLocal("La nota está fuera del rango permitido.");
        return;
      }
    }
    onSubmit(evento, datos);
  }

  return (
    <form className="space-y-4" onSubmit={enviar}>
      {requiere ? (
        <>
          <p className="text-sm text-slate-700">
            <span className="font-medium">Requiere certificado:</span> Sí
          </p>
          <Alert tono="aviso">
            Evidencia obligatoria. Debe adjuntar al menos un PDF o imagen antes de completar este
            cumplimiento.
          </Alert>
        </>
      ) : null}
      <Field etiqueta="Fecha de realización">
        <input
          type="date"
          className={inputClass}
          required
          value={datos.fecha_realizacion}
          onChange={(e) => setDatos((prev) => ({ ...prev, fecha_realizacion: e.target.value }))}
        />
      </Field>
      <Field etiqueta="Resultado">
        <select
          className={inputClass}
          value={datos.resultado}
          onChange={(e) => setDatos((prev) => ({ ...prev, resultado: e.target.value }))}
        >
          <option value="APROBADO">Aprobado</option>
        </select>
      </Field>
      <Field etiqueta="Horas efectivas">
        <input
          type="number"
          min="0.01"
          step="0.01"
          className={inputClass}
          required
          value={datos.horas_efectivas}
          onChange={(e) => setDatos((prev) => ({ ...prev, horas_efectivas: e.target.value }))}
        />
      </Field>
      {requiereEval ? (
        <>
          <Field etiqueta={`Nota (mínima ${minima.toFixed(2).replace(".", ",")})`}>
            <input
              type="number"
              min={0}
              max={5}
              step="0.01"
              className={inputClass}
              required
              value={datos.nota_evaluacion}
              onChange={(e) => setDatos((prev) => ({ ...prev, nota_evaluacion: e.target.value }))}
            />
            <p className="mt-1 text-xs text-slate-500">Escala 0 a 5.</p>
          </Field>
          <Field etiqueta="Resultado de la evaluación">
            <input
              className={inputClass}
              readOnly
              disabled
              value={
                evalAprobada === null ? "—" : evalAprobada ? "Aprobado" : "No aprobado"
              }
            />
          </Field>
        </>
      ) : null}
      <Field etiqueta="Observaciones">
        <input
          className={inputClass}
          value={datos.observaciones}
          onChange={(e) => setDatos((prev) => ({ ...prev, observaciones: e.target.value }))}
        />
      </Field>
      <Field etiqueta="Fecha de vencimiento">
        <input
          className={inputClass}
          readOnly
          disabled
          value={
            item
              ? item.fecha_vencimiento
                ? `${formatoFecha(item.fecha_vencimiento)} · ${item.etiqueta_periodicidad}`
                : "Sin vencimiento"
              : "Se calcula al elegir la fecha"
          }
        />
      </Field>
      <Field etiqueta={requiere ? "Evidencia (obligatoria)" : "Evidencia (opcional)"}>
        <input
          type="file"
          className={inputClass}
          accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
          multiple
          onChange={(e) =>
            setDatos((prev) => ({ ...prev, archivos: Array.from(e.target.files ?? []) }))
          }
        />
        <p className="mt-1 text-xs text-slate-500">PDF, JPG o PNG. Máximo 10 MB por archivo.</p>
      </Field>
      {cargados.length > 0 ? (
        <div>
          <p className="mb-1 text-sm font-medium text-slate-700">Archivos ya cargados</p>
          <ListaEvidencias
            soportes={cargados}
            puedeEliminar
            onEliminado={(id) => {
              setCargados((prev) => prev.filter((s) => s.soporte_id !== id));
              onSoporteEliminado?.(id);
            }}
            onError={onError}
          />
        </div>
      ) : null}
      {avisoLocal ? <Alert tono="error">{avisoLocal}</Alert> : null}
      {item && !item.puede_registrar && item.motivo && !modoEdicion ? (
        <Alert tono="aviso">{item.motivo}</Alert>
      ) : null}
      {cumplimientoId ? null : requiere ? (
        <p className="text-xs text-slate-500">
          Primero debe existir un borrador de asistencia para adjuntar el archivo.
        </p>
      ) : null}
      <div className="flex justify-end gap-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit" disabled={enviando || (!modoEdicion && item ? !item.puede_registrar : false)}>
          {enviando ? "Guardando…" : modoEdicion ? "Guardar cambios" : "Registrar cumplimiento"}
        </Button>
      </div>
    </form>
  );
}
