"use client";

import { FormEvent, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type { Capacitacion, CargoCorporativo, FilaMatriz, ItemCatalogo } from "@/lib/tipos";

export type DatosMatriz = {
  capacitacion_id: string;
  cargo_id_ext: string;
  area_id: string;
  proceso_id: string;
  ambito: string;
  proyecto: string;
  periodicidad_id: string;
  obligatoria: boolean;
  activa: boolean;
};

function vacio(): DatosMatriz {
  return {
    capacitacion_id: "",
    cargo_id_ext: "",
    area_id: "",
    proceso_id: "",
    ambito: "",
    proyecto: "",
    periodicidad_id: "",
    obligatoria: true,
    activa: true,
  };
}

function desdeItem(item: FilaMatriz): DatosMatriz {
  return {
    capacitacion_id: String(item.capacitacion_id),
    cargo_id_ext: item.cargo_id_ext ? String(item.cargo_id_ext) : "",
    area_id: item.area_id ? String(item.area_id) : "",
    proceso_id: item.proceso_id ? String(item.proceso_id) : "",
    ambito: item.ambito ?? "",
    proyecto: item.proyecto ?? "",
    periodicidad_id: item.periodicidad_id ? String(item.periodicidad_id) : "",
    obligatoria: item.obligatoria,
    activa: item.activa,
  };
}

export function FormularioMatriz({
  inicial,
  capacitaciones,
  cargos,
  areas,
  procesos,
  periodicidades,
  onCancelar,
  onGuardar,
}: {
  inicial: FilaMatriz | null;
  capacitaciones: Capacitacion[];
  cargos: CargoCorporativo[];
  areas: ItemCatalogo[];
  procesos: ItemCatalogo[];
  periodicidades: ItemCatalogo[];
  onCancelar: () => void;
  onGuardar: (evento: FormEvent, datos: DatosMatriz) => void | Promise<void>;
}) {
  const base = useMemo(() => (inicial ? desdeItem(inicial) : vacio()), [inicial]);
  const [datos, setDatos] = useState<DatosMatriz>(base);

  function set<K extends keyof DatosMatriz>(clave: K, valor: DatosMatriz[K]) {
    setDatos((prev) => ({ ...prev, [clave]: valor }));
  }

  return (
    <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => void onGuardar(e, datos)}>
      <Field etiqueta="Capacitación">
        <select
          className={inputClass}
          required
          value={datos.capacitacion_id}
          onChange={(e) => set("capacitacion_id", e.target.value)}
        >
          <option value="">Seleccione</option>
          {capacitaciones.map((c) => (
            <option key={c.capacitacion_id} value={c.capacitacion_id}>
              {c.codigo} — {c.nombre}
            </option>
          ))}
        </select>
      </Field>
      <Field etiqueta="Cargo (personal corporativo)">
        <select className={inputClass} value={datos.cargo_id_ext} onChange={(e) => set("cargo_id_ext", e.target.value)}>
          <option value="">Cualquier cargo</option>
          {cargos.map((c) => (
            <option key={c.cargo_id} value={c.cargo_id}>
              {c.nombre_cargo}
            </option>
          ))}
        </select>
      </Field>
      <Field etiqueta="Área">
        <select className={inputClass} value={datos.area_id} onChange={(e) => set("area_id", e.target.value)}>
          <option value="">Sin área</option>
          {areas.map((a) => (
            <option key={String(a.area_id)} value={String(a.area_id)}>
              {String(a.nombre)}
            </option>
          ))}
        </select>
      </Field>
      <Field etiqueta="Proceso">
        <select className={inputClass} value={datos.proceso_id} onChange={(e) => set("proceso_id", e.target.value)}>
          <option value="">Sin proceso</option>
          {procesos.map((p) => (
            <option key={String(p.proceso_id)} value={String(p.proceso_id)}>
              {String(p.nombre)}
            </option>
          ))}
        </select>
      </Field>
      <Field etiqueta="Ámbito">
        <select className={inputClass} value={datos.ambito} onChange={(e) => set("ambito", e.target.value)}>
          <option value="">Sin ámbito</option>
          <option value="ADMINISTRACION">Administración</option>
          <option value="PROYECTO">Proyecto</option>
        </select>
      </Field>
      <Field etiqueta="Proyecto (texto, como en personal)">
        <input className={inputClass} value={datos.proyecto} onChange={(e) => set("proyecto", e.target.value)} />
      </Field>
      <Field etiqueta="Periodicidad (override)">
        <select className={inputClass} value={datos.periodicidad_id} onChange={(e) => set("periodicidad_id", e.target.value)}>
          <option value="">Usar la de la capacitación</option>
          {periodicidades.map((p) => (
            <option key={String(p.periodicidad_id)} value={String(p.periodicidad_id)}>
              {String(p.nombre)}
            </option>
          ))}
        </select>
      </Field>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" checked={datos.obligatoria} onChange={(e) => set("obligatoria", e.target.checked)} />
        Obligatoria
      </label>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" checked={datos.activa} onChange={(e) => set("activa", e.target.checked)} />
        Activa
      </label>
      <div className="flex justify-end gap-2 sm:col-span-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Guardar</Button>
      </div>
    </form>
  );
}
