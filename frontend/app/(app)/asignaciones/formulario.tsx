"use client";

import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type { Asignacion, Capacitacion, PersonaCorporativa } from "@/lib/tipos";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";

export type DatosAsignacion = {
  persona_id_ext: string;
  persona_etiqueta: string;
  capacitacion_id: string;
  fecha_limite_cumplimiento: string;
  fecha_asignacion: string;
};

export function vacioAsignacion(): DatosAsignacion {
  const hoy = new Date();
  const iso = [
    hoy.getFullYear(),
    String(hoy.getMonth() + 1).padStart(2, "0"),
    String(hoy.getDate()).padStart(2, "0"),
  ].join("-");
  return {
    persona_id_ext: "",
    persona_etiqueta: "",
    capacitacion_id: "",
    fecha_limite_cumplimiento: "",
    fecha_asignacion: iso,
  };
}

export function desdeAsignacion(item: Asignacion): DatosAsignacion {
  return {
    persona_id_ext: String(item.persona_id_ext),
    persona_etiqueta: item.persona_nombre
      ? `${item.persona_nombre}${item.numero_documento ? ` · ${item.numero_documento}` : ""}`
      : String(item.persona_id_ext),
    capacitacion_id: String(item.capacitacion_id),
    fecha_limite_cumplimiento: item.fecha_limite_cumplimiento.slice(0, 10),
    fecha_asignacion: item.fecha_asignacion.slice(0, 10),
  };
}

export function FormularioAsignacion({
  inicial,
  capacitaciones,
  soloFecha,
  onSubmit,
  onCancelar,
}: {
  inicial?: Asignacion | null;
  capacitaciones: Capacitacion[];
  soloFecha?: boolean;
  onSubmit: (evento: FormEvent, datos: DatosAsignacion) => void;
  onCancelar: () => void;
}) {
  const [datos, setDatos] = useState<DatosAsignacion>(
    inicial ? desdeAsignacion(inicial) : vacioAsignacion(),
  );
  const [buscarPersona, setBuscarPersona] = useState("");
  const [personas, setPersonas] = useState<PersonaCorporativa[]>([]);

  useEffect(() => {
    if (soloFecha) {
      return;
    }

    const id = window.setTimeout(() => {
      void (async () => {
        const r = await apiGet<ListaPaginada<PersonaCorporativa>>(
          withQuery("/api/personal", {
            page: 1,
            per_page: 8,
            buscar: buscarPersona,
            estado: "Activo",
          }),
        );
        if (r.success && r.data) {
          setPersonas(r.data.items);
        }
      })();
    }, 300);

    return () => window.clearTimeout(id);
  }, [buscarPersona, soloFecha]);

  function set(campo: keyof DatosAsignacion, valor: string) {
    setDatos((actual) => ({ ...actual, [campo]: valor }));
  }

  return (
    <form className="space-y-4" onSubmit={(evento) => onSubmit(evento, datos)}>
      {soloFecha ? (
        <p className="text-sm text-slate-600">
          {datos.persona_etiqueta} · {capacitaciones.find((c) => String(c.capacitacion_id) === datos.capacitacion_id)?.nombre
            ?? inicial?.capacitacion_nombre}
        </p>
      ) : (
        <>
          <Field etiqueta="Trabajador">
            <input
              className={inputClass}
              value={buscarPersona}
              onChange={(e) => setBuscarPersona(e.target.value)}
              placeholder="Buscar por documento o nombre"
            />
          </Field>
          <div className="max-h-40 overflow-y-auto rounded-lg border border-slate-200">
            {personas.map((persona) => (
              <button
                key={persona.persona_id}
                type="button"
                className={`block w-full px-3 py-2 text-left text-sm hover:bg-hseq-50 ${
                  datos.persona_id_ext === String(persona.persona_id) ? "bg-hseq-50 font-medium" : ""
                }`}
                onClick={() => {
                  set("persona_id_ext", String(persona.persona_id));
                  set("persona_etiqueta", `${persona.nombre_completo} · ${persona.numero_documento}`);
                }}
              >
                {persona.nombre_completo}
                <span className="ml-2 text-xs text-slate-500">{persona.numero_documento}</span>
              </button>
            ))}
          </div>
          {datos.persona_etiqueta ? (
            <p className="text-sm text-hseq-800">Seleccionado: {datos.persona_etiqueta}</p>
          ) : (
            <p className="text-xs text-slate-500">Seleccione un trabajador de la lista.</p>
          )}

          <Field etiqueta="Capacitación">
            <select
              className={inputClass}
              required
              value={datos.capacitacion_id}
              onChange={(e) => set("capacitacion_id", e.target.value)}
            >
              <option value="">Seleccione</option>
              {capacitaciones.map((cap) => (
                <option key={cap.capacitacion_id} value={cap.capacitacion_id}>
                  {cap.codigo} — {cap.nombre}
                </option>
              ))}
            </select>
          </Field>

          <Field etiqueta="Fecha de asignación">
            <input
              className={inputClass}
              type="date"
              required
              value={datos.fecha_asignacion}
              onChange={(e) => set("fecha_asignacion", e.target.value)}
            />
          </Field>
        </>
      )}

      <Field etiqueta="Fecha límite de cumplimiento">
        <input
          className={inputClass}
          type="date"
          required
          value={datos.fecha_limite_cumplimiento}
          onChange={(e) => set("fecha_limite_cumplimiento", e.target.value)}
        />
        <span className="mt-1 block text-xs text-slate-500">
          Plazo para realizar el curso. La alerta de “próxima a vencer” aparece sola 10 días
          calendario antes de esta fecha.
        </span>
      </Field>

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">{soloFecha ? "Guardar fecha" : "Asignar capacitación"}</Button>
      </div>
    </form>
  );
}
