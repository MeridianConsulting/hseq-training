"use client";

import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type {
  Asignacion,
  Capacitacion,
  CapacitacionAplicable,
  PerfilTrabajador,
  PersonaCorporativa,
} from "@/lib/tipos";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";

export type DatosAsignacion = {
  persona_id_ext: string;
  persona_etiqueta: string;
  capacitacion_id: string;
  capacitacion_ids: string[];
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
    capacitacion_ids: [],
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
    capacitacion_ids: [String(item.capacitacion_id)],
    fecha_limite_cumplimiento: item.fecha_limite_cumplimiento.slice(0, 10),
    fecha_asignacion: item.fecha_asignacion.slice(0, 10),
  };
}

function etiquetaProcesos(persona: PersonaCorporativa): string {
  const nombres = (persona.procesos ?? []).map((p) => p.nombre).filter(Boolean);
  return nombres.length > 0 ? nombres.join(", ") : "—";
}

function etiquetaCap(item: CapacitacionAplicable): string {
  if (item.capacitacion_codigo && item.capacitacion_nombre) {
    return `${item.capacitacion_codigo} — ${item.capacitacion_nombre}`;
  }
  return item.capacitacion_nombre ?? item.capacitacion_codigo ?? "—";
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
  const [ficha, setFicha] = useState<PersonaCorporativa | null>(null);
  const [aplicables, setAplicables] = useState<CapacitacionAplicable[]>([]);
  const [cargandoContexto, setCargandoContexto] = useState(false);

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

  useEffect(() => {
    if (soloFecha || !datos.persona_id_ext) {
      return;
    }
    const personaId = Number(datos.persona_id_ext);
    if (!Number.isFinite(personaId) || personaId < 1) {
      return;
    }
    setCargandoContexto(true);
    void (async () => {
      const r = await apiGet<PerfilTrabajador>(`/api/personal/${personaId}/perfil`);
      setCargandoContexto(false);
      if (!r.success || !r.data) {
        setFicha(null);
        setAplicables([]);
        return;
      }
      setFicha(r.data.ficha);
      setAplicables(r.data.aplicables);
    })();
  }, [datos.persona_id_ext, soloFecha]);

  function set(campo: keyof DatosAsignacion, valor: string) {
    setDatos((actual) => ({ ...actual, [campo]: valor }));
  }

  function toggleCap(id: string) {
    setDatos((actual) => {
      const tiene = actual.capacitacion_ids.includes(id);
      return {
        ...actual,
        capacitacion_ids: tiene
          ? actual.capacitacion_ids.filter((c) => c !== id)
          : [...actual.capacitacion_ids, id],
        capacitacion_id: tiene
          ? actual.capacitacion_ids.filter((c) => c !== id)[0] ?? ""
          : actual.capacitacion_id || id,
      };
    });
  }

  return (
    <form className="space-y-4" onSubmit={(evento) => onSubmit(evento, datos)}>
      {soloFecha ? (
        <p className="text-sm text-slate-600">
          {datos.persona_etiqueta} ·{" "}
          {capacitaciones.find((c) => String(c.capacitacion_id) === datos.capacitacion_id)?.nombre
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
                  setDatos((actual) => ({
                    ...actual,
                    persona_id_ext: String(persona.persona_id),
                    persona_etiqueta: `${persona.nombre_completo} · ${persona.numero_documento}`,
                    capacitacion_ids: [],
                    capacitacion_id: "",
                  }));
                  setFicha(persona);
                  setAplicables([]);
                }}
              >
                {persona.nombre_completo}
                <span className="ml-2 text-xs text-slate-500">{persona.numero_documento}</span>
              </button>
            ))}
          </div>

          {ficha ? (
            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
              <p className="font-medium text-hseq-900">{ficha.nombre_completo}</p>
              <p>Documento: {ficha.numero_documento}</p>
              <p>Cargo: {ficha.cargo ?? "—"}</p>
              <p>Proyecto: {ficha.proyecto ?? "—"}</p>
              <p>Estado corporativo: {ficha.estado}</p>
              <p>Proceso: {etiquetaProcesos(ficha)}</p>
            </div>
          ) : (
            <p className="text-xs text-slate-500">Seleccione un trabajador de la lista.</p>
          )}

          <Field etiqueta="Capacitaciones aplicables">
            {cargandoContexto ? (
              <p className="text-sm text-slate-500">Consultando la matriz…</p>
            ) : !ficha ? (
              <p className="text-sm text-slate-500">Seleccione un trabajador para ver las aplicables.</p>
            ) : aplicables.length === 0 ? (
              <p className="text-sm text-slate-500">
                No hay capacitaciones aplicables según la matriz para este cargo o proyecto.
              </p>
            ) : (
              <div className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                {aplicables.map((item) => {
                  const id = String(item.capacitacion_id ?? "");
                  if (!id) {
                    return null;
                  }
                  return (
                    <label key={id} className="flex cursor-pointer items-start gap-2 rounded px-2 py-1.5 text-sm hover:bg-hseq-50">
                      <input
                        type="checkbox"
                        className="mt-0.5"
                        checked={datos.capacitacion_ids.includes(id)}
                        onChange={() => toggleCap(id)}
                      />
                      <span>
                        {etiquetaCap(item)}
                        <span className="block text-xs text-slate-500">
                          Aplicable: Sí · Origen: Matriz de Aplicabilidad
                          {item.proceso_nombre ? ` · ${item.proceso_nombre}` : ""}
                        </span>
                      </span>
                    </label>
                  );
                })}
              </div>
            )}
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
          Plazo para realizar el curso.
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
