"use client";

import { FormEvent, useEffect, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { apiGet, withQuery } from "@/lib/api";
import type { PreviewCumplimiento } from "@/lib/tipos";

export type DatosCumplimiento = {
  fecha_realizacion: string;
  resultado: string;
  horas_efectivas: string;
  observaciones: string;
};

export function vacioCumplimiento(fechaDefault = ""): DatosCumplimiento {
  return {
    fecha_realizacion: fechaDefault,
    resultado: "APROBADO",
    horas_efectivas: "",
    observaciones: "",
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
  fechaDefault,
  onSubmit,
  onCancelar,
  enviando = false,
  modoEdicion = false,
}: {
  asignacionId: number;
  sesionId: number;
  fechaDefault?: string;
  onSubmit: (evento: FormEvent, datos: DatosCumplimiento) => void;
  onCancelar: () => void;
  enviando?: boolean;
  modoEdicion?: boolean;
}) {
  const [datos, setDatos] = useState<DatosCumplimiento>(vacioCumplimiento(fechaDefault ?? ""));
  const [preview, setPreview] = useState<PreviewCumplimiento | null>(null);

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

  return (
    <form className="space-y-4" onSubmit={(e) => onSubmit(e, datos)}>
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
      {item && !item.puede_registrar && item.motivo && !modoEdicion ? (
        <Alert tono="aviso">{item.motivo}</Alert>
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
