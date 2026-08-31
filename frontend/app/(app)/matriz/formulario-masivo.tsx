"use client";

import { FormEvent, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type { Capacitacion, CargoCorporativo, ItemCatalogo } from "@/lib/tipos";

export type DatosMatrizMasiva = {
  capacitacion_id: string;
  cargo_ids_ext: string[];
  proceso_id: string;
  proyecto: string;
  periodicidad_id: string;
  obligatoria: boolean;
};

export type ErroresMatrizMasiva = Partial<Record<"capacitacion_id" | "cargo_ids_ext" | "periodicidad_id", string>>;

function vacio(): DatosMatrizMasiva {
  return {
    capacitacion_id: "",
    cargo_ids_ext: [],
    proceso_id: "",
    proyecto: "",
    periodicidad_id: "",
    obligatoria: true,
  };
}

export function validarMatrizMasiva(datos: DatosMatrizMasiva): ErroresMatrizMasiva {
  const errores: ErroresMatrizMasiva = {};
  if (!datos.capacitacion_id) {
    errores.capacitacion_id = "Seleccione una capacitación.";
  }
  if (datos.cargo_ids_ext.length < 1) {
    errores.cargo_ids_ext = "Seleccione al menos un cargo.";
  }
  if (!datos.periodicidad_id) {
    errores.periodicidad_id = "Seleccione una periodicidad.";
  }
  return errores;
}

export function FormularioMatrizMasiva({
  capacitaciones,
  cargos,
  procesos,
  periodicidades,
  onCancelar,
  onGuardar,
}: {
  capacitaciones: Capacitacion[];
  cargos: CargoCorporativo[];
  procesos: ItemCatalogo[];
  periodicidades: ItemCatalogo[];
  onCancelar: () => void;
  onGuardar: (evento: FormEvent, datos: DatosMatrizMasiva) => void | Promise<void>;
}) {
  const [datos, setDatos] = useState<DatosMatrizMasiva>(vacio());
  const [errores, setErrores] = useState<ErroresMatrizMasiva>({});

  function toggleCargo(id: string) {
    setDatos((prev) => {
      const tiene = prev.cargo_ids_ext.includes(id);
      return {
        ...prev,
        cargo_ids_ext: tiene ? prev.cargo_ids_ext.filter((c) => c !== id) : [...prev.cargo_ids_ext, id],
      };
    });
    setErrores((prev) => ({ ...prev, cargo_ids_ext: undefined }));
  }

  function enviar(evento: FormEvent) {
    const locales = validarMatrizMasiva(datos);
    setErrores(locales);
    if (Object.keys(locales).length > 0) {
      evento.preventDefault();
      return;
    }
    void onGuardar(evento, datos);
  }

  return (
    <form className="grid gap-4" onSubmit={enviar}>
      <p className="text-sm text-slate-600">
        Asocia una capacitación a varios cargos en el mismo proceso y proyecto, con la misma periodicidad y
        obligatoriedad.
      </p>
      <Field etiqueta="Capacitación" error={errores.capacitacion_id}>
        <select
          className={inputClass}
          required
          value={datos.capacitacion_id}
          onChange={(e) => {
            setDatos((p) => ({ ...p, capacitacion_id: e.target.value }));
            setErrores((p) => ({ ...p, capacitacion_id: undefined }));
          }}
        >
          <option value="">Seleccione</option>
          {capacitaciones.map((c) => (
            <option key={c.capacitacion_id} value={c.capacitacion_id}>
              {c.codigo} — {c.nombre}
            </option>
          ))}
        </select>
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field etiqueta="Proceso">
          <select
            className={inputClass}
            value={datos.proceso_id}
            onChange={(e) => setDatos((p) => ({ ...p, proceso_id: e.target.value }))}
          >
            <option value="">Sin proceso</option>
            {procesos.map((p) => (
              <option key={String(p.proceso_id)} value={String(p.proceso_id)}>
                {String(p.nombre)}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Proyecto">
          <input
            className={inputClass}
            value={datos.proyecto}
            onChange={(e) => setDatos((p) => ({ ...p, proyecto: e.target.value }))}
            maxLength={120}
          />
        </Field>
        <Field etiqueta="Periodicidad" error={errores.periodicidad_id}>
          <select
            className={inputClass}
            required
            value={datos.periodicidad_id}
            onChange={(e) => {
              setDatos((p) => ({ ...p, periodicidad_id: e.target.value }));
              setErrores((p) => ({ ...p, periodicidad_id: undefined }));
            }}
          >
            <option value="">Seleccione</option>
            {periodicidades.map((p) => (
              <option key={String(p.periodicidad_id)} value={String(p.periodicidad_id)}>
                {String(p.nombre)}
              </option>
            ))}
          </select>
        </Field>
        <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={datos.obligatoria}
            onChange={(e) => setDatos((p) => ({ ...p, obligatoria: e.target.checked }))}
          />
          Obligatoria
        </label>
      </div>
      <Field etiqueta="Cargos" error={errores.cargo_ids_ext}>
        <div className="max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2">
          {cargos.length === 0 ? (
            <p className="px-2 py-1 text-sm text-slate-500">No hay cargos en el maestro.</p>
          ) : (
            cargos.map((cargo) => {
              const id = String(cargo.cargo_id);
              return (
                <label key={cargo.cargo_id} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50">
                  <input
                    type="checkbox"
                    checked={datos.cargo_ids_ext.includes(id)}
                    onChange={() => toggleCargo(id)}
                  />
                  {cargo.nombre_cargo}
                </label>
              );
            })
          )}
        </div>
        <p className="mt-1 text-xs text-slate-500">{datos.cargo_ids_ext.length} cargo(s) seleccionado(s).</p>
      </Field>
      <div className="flex justify-end gap-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Asociar capacitación</Button>
      </div>
    </form>
  );
}
