"use client";

import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type { Capacitacion, PersonaCorporativa } from "@/lib/tipos";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";

export type DatosAsignacionMasiva = {
  capacitacion_id: string;
  persona_ids_ext: string[];
  fecha_asignacion: string;
  fecha_limite_cumplimiento: string;
};

export type ErroresAsignacionMasiva = Partial<
  Record<"capacitacion_id" | "persona_ids_ext" | "fecha_asignacion" | "fecha_limite_cumplimiento", string>
>;

function vacio(): DatosAsignacionMasiva {
  return {
    capacitacion_id: "",
    persona_ids_ext: [],
    fecha_asignacion: "",
    fecha_limite_cumplimiento: "",
  };
}

export function validarAsignacionMasiva(datos: DatosAsignacionMasiva): ErroresAsignacionMasiva {
  const errores: ErroresAsignacionMasiva = {};
  if (!datos.capacitacion_id) {
    errores.capacitacion_id = "Seleccione una capacitación.";
  }
  if (datos.persona_ids_ext.length < 1) {
    errores.persona_ids_ext = "Seleccione al menos un trabajador.";
  }
  if (!datos.fecha_asignacion) {
    errores.fecha_asignacion = "Indique la fecha desde.";
  }
  if (!datos.fecha_limite_cumplimiento) {
    errores.fecha_limite_cumplimiento = "Indique la fecha hasta.";
  }
  if (
    datos.fecha_asignacion &&
    datos.fecha_limite_cumplimiento &&
    datos.fecha_limite_cumplimiento < datos.fecha_asignacion
  ) {
    errores.fecha_limite_cumplimiento = "La fecha hasta no puede ser anterior a la fecha desde.";
  }
  return errores;
}

export function FormularioAsignacionMasiva({
  capacitaciones,
  onCancelar,
  onGuardar,
}: {
  capacitaciones: Capacitacion[];
  onCancelar: () => void;
  onGuardar: (evento: FormEvent, datos: DatosAsignacionMasiva) => void | Promise<void>;
}) {
  const [datos, setDatos] = useState<DatosAsignacionMasiva>(vacio());
  const [errores, setErrores] = useState<ErroresAsignacionMasiva>({});
  const [buscarPersona, setBuscarPersona] = useState("");
  const [personas, setPersonas] = useState<PersonaCorporativa[]>([]);

  useEffect(() => {
    const id = window.setTimeout(() => {
      void (async () => {
        const r = await apiGet<ListaPaginada<PersonaCorporativa>>(
          withQuery("/api/personal", {
            page: 1,
            per_page: 50,
            buscar: buscarPersona.trim() || undefined,
            estado: "Activo",
          }),
        );
        if (r.success && r.data) {
          setPersonas(r.data.items);
        }
      })();
    }, 300);

    return () => window.clearTimeout(id);
  }, [buscarPersona]);

  function togglePersona(id: string) {
    setDatos((prev) => {
      const tiene = prev.persona_ids_ext.includes(id);
      return {
        ...prev,
        persona_ids_ext: tiene
          ? prev.persona_ids_ext.filter((p) => p !== id)
          : [...prev.persona_ids_ext, id],
      };
    });
    setErrores((prev) => ({ ...prev, persona_ids_ext: undefined }));
  }

  function enviar(evento: FormEvent) {
    const locales = validarAsignacionMasiva(datos);
    setErrores(locales);
    if (Object.keys(locales).length > 0) {
      evento.preventDefault();
      return;
    }
    void onGuardar(evento, datos);
  }

  const cap = capacitaciones.find((c) => String(c.capacitacion_id) === datos.capacitacion_id);

  return (
    <form className="space-y-4" onSubmit={enviar}>
      <Field etiqueta="Capacitación" error={errores.capacitacion_id}>
        <select
          className={inputClass}
          value={datos.capacitacion_id}
          onChange={(e) => {
            setDatos((prev) => ({ ...prev, capacitacion_id: e.target.value }));
            setErrores((prev) => ({ ...prev, capacitacion_id: undefined }));
          }}
        >
          <option value="">Seleccione</option>
          {capacitaciones.map((item) => (
            <option key={item.capacitacion_id} value={item.capacitacion_id}>
              {item.codigo} — {item.nombre}
            </option>
          ))}
        </select>
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field etiqueta="Fecha desde" error={errores.fecha_asignacion}>
          <input
            className={inputClass}
            type="date"
            required
            value={datos.fecha_asignacion}
            onChange={(e) =>
              setDatos((prev) => ({ ...prev, fecha_asignacion: e.target.value }))
            }
          />
        </Field>
        <Field etiqueta="Fecha hasta" error={errores.fecha_limite_cumplimiento}>
          <input
            className={inputClass}
            type="date"
            required
            value={datos.fecha_limite_cumplimiento}
            onChange={(e) =>
              setDatos((prev) => ({ ...prev, fecha_limite_cumplimiento: e.target.value }))
            }
          />
        </Field>
      </div>
      <p className="text-xs text-slate-500">
        El mismo período aplica a todas las personas seleccionadas. Solo se asignan quienes
        coincidan con la Matriz de Aplicabilidad (cargo, proceso y proyecto); el resto se omite
        como no aplicable. HSEQ define el plazo; no se calcula desde la vigencia ni desde el Plan
        anual.
      </p>

      <Field etiqueta="Trabajadores activos" error={errores.persona_ids_ext}>
        <input
          className={inputClass}
          value={buscarPersona}
          onChange={(e) => setBuscarPersona(e.target.value)}
          placeholder="Buscar por documento o nombre"
        />
      </Field>
      <div className="max-h-56 overflow-y-auto rounded-lg border border-slate-200">
        {personas.length === 0 ? (
          <p className="px-3 py-4 text-sm text-slate-500">No hay trabajadores para mostrar.</p>
        ) : (
          personas.map((persona) => {
            const id = String(persona.persona_id);
            const marcado = datos.persona_ids_ext.includes(id);
            return (
              <label
                key={persona.persona_id}
                className={`flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-hseq-50 ${
                  marcado ? "bg-hseq-50" : ""
                }`}
              >
                <input
                  type="checkbox"
                  checked={marcado}
                  onChange={() => togglePersona(id)}
                />
                <span>
                  {persona.nombre_completo}
                  <span className="ml-2 text-xs text-slate-500">{persona.numero_documento}</span>
                  <span className="block text-xs text-slate-500">
                    {persona.cargo ?? "Sin cargo"}
                    {persona.proyecto ? ` · ${persona.proyecto}` : ""}
                    {` · ${persona.estado}`}
                  </span>
                </span>
              </label>
            );
          })
        )}
      </div>

      {datos.capacitacion_id && datos.persona_ids_ext.length > 0 && datos.fecha_asignacion && datos.fecha_limite_cumplimiento ? (
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
          <p className="font-medium text-slate-900">Resumen antes de confirmar</p>
          <p>
            Capacitación: {cap ? `${cap.codigo} — ${cap.nombre}` : datos.capacitacion_id}
          </p>
          <p>
            Período: {datos.fecha_asignacion} → {datos.fecha_limite_cumplimiento}
          </p>
          <p>Personas seleccionadas: {datos.persona_ids_ext.length}</p>
        </div>
      ) : (
        <p className="text-xs text-slate-500">
          {datos.persona_ids_ext.length} trabajador(es) seleccionado(s).
        </p>
      )}

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Asignar a seleccionados</Button>
      </div>
    </form>
  );
}
