"use client";

import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type { Capacitacion, PersonaCorporativa } from "@/lib/tipos";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";

export type DatosAsignacionMasiva = {
  capacitacion_id: string;
  persona_ids_ext: string[];
  fecha_limite_cumplimiento: string;
};

export type ErroresAsignacionMasiva = Partial<Record<"capacitacion_id" | "persona_ids_ext", string>>;

function vacio(): DatosAsignacionMasiva {
  return {
    capacitacion_id: "",
    persona_ids_ext: [],
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
          {capacitaciones.map((cap) => (
            <option key={cap.capacitacion_id} value={cap.capacitacion_id}>
              {cap.codigo} — {cap.nombre}
            </option>
          ))}
        </select>
      </Field>

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
                </span>
              </label>
            );
          })
        )}
      </div>
      <p className="text-xs text-slate-500">
        {datos.persona_ids_ext.length} trabajador(es) seleccionado(s).
      </p>

      <Field etiqueta="Fecha límite de cumplimiento (opcional)">
        <input
          className={inputClass}
          type="date"
          value={datos.fecha_limite_cumplimiento}
          onChange={(e) =>
            setDatos((prev) => ({ ...prev, fecha_limite_cumplimiento: e.target.value }))
          }
        />
        <span className="mt-1 block text-xs text-slate-500">
          Si la deja vacía, se calcula con la periodicidad de la capacitación.
        </span>
      </Field>

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Asignar a seleccionados</Button>
      </div>
    </form>
  );
}
