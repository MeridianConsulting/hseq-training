"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type { ApiErrorMap } from "@/lib/api";
import type { Capacitacion, ItemCatalogo } from "@/lib/tipos";
import { conValorHistorico } from "@/lib/catalogos";

export type DatosCapacitacion = {
  codigo: string;
  nombre: string;
  objetivo: string;
  descripcion_temario: string;
  categoria_id: string;
  tipo_capacitacion_id: string;
  duracion_estimada_horas: string;
  criticidad: "" | "BAJA" | "MEDIA" | "ALTA";
  es_tarea_critica: boolean;
  responsable: string;
  proveedor_default_id: string;
  periodicidad_default_id: string;
  vigencia_id: string;
  modalidad_default_id: string;
  evaluacion: boolean;
  nota_minima: string;
  certificado: boolean;
  requiere_listado_asistencia: boolean;
  fuente_normativa_id: string;
  estado: "ACTIVA" | "INACTIVA";
};

export type ErroresCapacitacion = Partial<Record<keyof DatosCapacitacion, string>>;

function vacio(): DatosCapacitacion {
  return {
    codigo: "",
    nombre: "",
    objetivo: "",
    descripcion_temario: "",
    categoria_id: "",
    tipo_capacitacion_id: "",
    duracion_estimada_horas: "",
    criticidad: "",
    es_tarea_critica: false,
    responsable: "",
    proveedor_default_id: "",
    periodicidad_default_id: "",
    vigencia_id: "",
    modalidad_default_id: "",
    evaluacion: false,
    nota_minima: "0",
    certificado: false,
    requiere_listado_asistencia: false,
    fuente_normativa_id: "",
    estado: "ACTIVA",
  };
}

function desdeItem(item: Capacitacion): DatosCapacitacion {
  return {
    codigo: item.codigo,
    nombre: item.nombre,
    objetivo: item.objetivo,
    descripcion_temario: item.descripcion_temario ?? "",
    categoria_id: item.categoria_id ? String(item.categoria_id) : "",
    tipo_capacitacion_id: item.tipo_capacitacion_id ? String(item.tipo_capacitacion_id) : "",
    duracion_estimada_horas: item.duracion_estimada_horas != null ? String(item.duracion_estimada_horas) : "",
    criticidad: item.criticidad,
    es_tarea_critica: item.es_tarea_critica,
    responsable: item.responsable ?? "",
    proveedor_default_id: item.proveedor_default_id ? String(item.proveedor_default_id) : "",
    periodicidad_default_id: item.periodicidad_default_id ? String(item.periodicidad_default_id) : "",
    vigencia_id: item.vigencia_id ? String(item.vigencia_id) : "",
    modalidad_default_id: item.modalidad_default_id ? String(item.modalidad_default_id) : "",
    evaluacion: item.evaluacion,
    nota_minima: item.nota_minima != null ? String(item.nota_minima) : "0",
    certificado: item.certificado,
    requiere_listado_asistencia: item.requiere_listado_asistencia,
    fuente_normativa_id: item.fuente_normativa_id ? String(item.fuente_normativa_id) : "",
    estado: item.estado,
  };
}

function opciones(items: ItemCatalogo[], pk: string) {
  return items.map((item) => (
    <option key={String(item[pk])} value={String(item[pk])}>
      {String(item.nombre ?? "")}
    </option>
  ));
}

export function validarDatosCapacitacion(datos: DatosCapacitacion): ErroresCapacitacion {
  const errores: ErroresCapacitacion = {};
  const horas = datos.duracion_estimada_horas.trim();

  if (!datos.codigo.trim()) {
    errores.codigo = "El código es obligatorio.";
  }
  if (!datos.nombre.trim()) {
    errores.nombre = "El nombre es obligatorio.";
  }
  if (!datos.objetivo.trim()) {
    errores.objetivo = "El objetivo es obligatorio.";
  }

  if (horas === "") {
    errores.duracion_estimada_horas = "La duración estimada es obligatoria.";
  } else if (!Number.isFinite(Number(horas))) {
    errores.duracion_estimada_horas = "La duración estimada debe ser un valor numérico.";
  } else if (Number(horas) <= 0) {
    errores.duracion_estimada_horas = "La duración debe ser mayor que cero.";
  }

  if (!datos.criticidad) {
    errores.criticidad = "Debe definir la criticidad de la capacitación.";
  }

  return errores;
}

function primerError(mapa: ApiErrorMap | null, campo: string): string | undefined {
  const lista = mapa?.[campo];
  return lista && lista.length > 0 ? lista[0] : undefined;
}

export function FormularioCapacitacion({
  inicial,
  catalogos,
  erroresApi,
  onCancelar,
  onGuardar,
}: {
  inicial: Capacitacion | null;
  catalogos: Record<string, ItemCatalogo[]>;
  erroresApi?: ApiErrorMap | null;
  onCancelar: () => void;
  onGuardar: (evento: FormEvent, datos: DatosCapacitacion) => void | Promise<void>;
}) {
  const base = useMemo(() => (inicial ? desdeItem(inicial) : vacio()), [inicial]);
  const [datos, setDatos] = useState<DatosCapacitacion>(base);

  const catalogosVisibles = useMemo(() => {
    if (!inicial) {
      return catalogos;
    }

    return {
      ...catalogos,
      categorias: conValorHistorico(
        catalogos.categorias ?? [],
        "categoria_id",
        inicial.categoria_id,
        inicial.categoria_nombre,
      ),
      "tipos-capacitacion": conValorHistorico(
        catalogos["tipos-capacitacion"] ?? [],
        "tipo_capacitacion_id",
        inicial.tipo_capacitacion_id,
        inicial.tipo_nombre,
      ),
      periodicidades: conValorHistorico(
        catalogos.periodicidades ?? [],
        "periodicidad_id",
        inicial.periodicidad_default_id,
        inicial.periodicidad_nombre,
      ),
      vigencias: conValorHistorico(
        catalogos.vigencias ?? [],
        "vigencia_id",
        inicial.vigencia_id,
        inicial.vigencia_nombre,
      ),
      modalidades: conValorHistorico(
        catalogos.modalidades ?? [],
        "modalidad_id",
        inicial.modalidad_default_id,
        inicial.modalidad_nombre,
      ),
      proveedores: conValorHistorico(
        catalogos.proveedores ?? [],
        "proveedor_id",
        inicial.proveedor_default_id,
        inicial.proveedor_nombre,
      ),
      "fuentes-normativas": conValorHistorico(
        catalogos["fuentes-normativas"] ?? [],
        "fuente_normativa_id",
        inicial.fuente_normativa_id,
        inicial.fuente_normativa_nombre,
      ),
    };
  }, [catalogos, inicial]);
  const [errores, setErrores] = useState<ErroresCapacitacion>({});

  useEffect(() => {
    if (!erroresApi) {
      return;
    }
    setErrores({
      duracion_estimada_horas: primerError(erroresApi, "duracion_estimada_horas"),
      criticidad: primerError(erroresApi, "criticidad"),
      codigo: primerError(erroresApi, "codigo"),
      nombre: primerError(erroresApi, "nombre"),
      objetivo: primerError(erroresApi, "objetivo"),
      estado: primerError(erroresApi, "estado"),
    });
  }, [erroresApi]);

  function set<K extends keyof DatosCapacitacion>(clave: K, valor: DatosCapacitacion[K]) {
    setDatos((prev) => ({ ...prev, [clave]: valor }));
    setErrores((prev) => ({ ...prev, [clave]: undefined }));
  }

  function enviar(evento: FormEvent) {
    const locales = validarDatosCapacitacion(datos);
    setErrores(locales);
    if (Object.keys(locales).length > 0) {
      evento.preventDefault();
      return;
    }
    void onGuardar(evento, datos);
  }

  return (
    <form className="grid gap-4 sm:grid-cols-2" noValidate onSubmit={enviar}>
      <p className="sm:col-span-2 text-sm font-semibold text-slate-800">Datos principales</p>
      <Field etiqueta="Código" error={errores.codigo}>
        <input className={inputClass} required value={datos.codigo} onChange={(e) => set("codigo", e.target.value)} />
      </Field>
      <Field etiqueta="Nombre de la capacitación" error={errores.nombre}>
        <input className={inputClass} required value={datos.nombre} onChange={(e) => set("nombre", e.target.value)} />
      </Field>
      <Field etiqueta="Objetivo" error={errores.objetivo}>
        <textarea
          className={inputClass}
          required
          rows={3}
          value={datos.objetivo}
          onChange={(e) => set("objetivo", e.target.value)}
        />
      </Field>
      <Field etiqueta="Duración estimada (horas)" error={errores.duracion_estimada_horas}>
        <input
          className={inputClass}
          type="number"
          min={0.01}
          step="0.5"
          value={datos.duracion_estimada_horas}
          onChange={(e) => set("duracion_estimada_horas", e.target.value)}
        />
      </Field>
      <Field etiqueta="Clasificación / categoría">
        <select className={inputClass} value={datos.categoria_id} onChange={(e) => set("categoria_id", e.target.value)}>
          <option value="">Sin categoría</option>
          {opciones(catalogosVisibles.categorias ?? [], "categoria_id")}
        </select>
      </Field>
      <Field etiqueta="Criticidad" error={errores.criticidad}>
        <select
          className={inputClass}
          value={datos.criticidad}
          onChange={(e) => set("criticidad", e.target.value as DatosCapacitacion["criticidad"])}
        >
          <option value="">Seleccione…</option>
          <option value="BAJA">Baja</option>
          <option value="MEDIA">Media</option>
          <option value="ALTA">Alta</option>
        </select>
      </Field>
      <Field etiqueta="Estado" error={errores.estado}>
        <select className={inputClass} value={datos.estado} onChange={(e) => set("estado", e.target.value as DatosCapacitacion["estado"])}>
          <option value="ACTIVA">Activa</option>
          <option value="INACTIVA">Inactiva</option>
        </select>
      </Field>

      <p className="sm:col-span-2 mt-2 text-sm font-semibold text-slate-800">Datos complementarios</p>
      <Field etiqueta="Temario">
        <textarea
          className={inputClass}
          rows={3}
          value={datos.descripcion_temario}
          onChange={(e) => set("descripcion_temario", e.target.value)}
        />
      </Field>
      <Field etiqueta="Tipo">
        <select className={inputClass} value={datos.tipo_capacitacion_id} onChange={(e) => set("tipo_capacitacion_id", e.target.value)}>
          <option value="">Sin tipo</option>
          {opciones(catalogosVisibles["tipos-capacitacion"] ?? [], "tipo_capacitacion_id")}
        </select>
      </Field>
      <Field etiqueta="Periodicidad (ciclo de repetición)">
        <select className={inputClass} value={datos.periodicidad_default_id} onChange={(e) => set("periodicidad_default_id", e.target.value)}>
          <option value="">No periódica</option>
          {opciones(catalogosVisibles.periodicidades ?? [], "periodicidad_id")}
        </select>
      </Field>
      <Field etiqueta="Vigencia (validez una vez tomada)">
        <select className={inputClass} value={datos.vigencia_id} onChange={(e) => set("vigencia_id", e.target.value)}>
          <option value="">No vence</option>
          {opciones(catalogosVisibles.vigencias ?? [], "vigencia_id")}
        </select>
      </Field>
      <Field etiqueta="Modalidad">
        <select className={inputClass} value={datos.modalidad_default_id} onChange={(e) => set("modalidad_default_id", e.target.value)}>
          <option value="">Sin modalidad</option>
          {opciones(catalogosVisibles.modalidades ?? [], "modalidad_id")}
        </select>
      </Field>
      <Field etiqueta="Proveedor">
        <select className={inputClass} value={datos.proveedor_default_id} onChange={(e) => set("proveedor_default_id", e.target.value)}>
          <option value="">Sin proveedor</option>
          {opciones(catalogosVisibles.proveedores ?? [], "proveedor_id")}
        </select>
      </Field>
      <Field etiqueta="Fuente normativa">
        <select className={inputClass} value={datos.fuente_normativa_id} onChange={(e) => set("fuente_normativa_id", e.target.value)}>
          <option value="">Sin fuente</option>
          {opciones(catalogosVisibles["fuentes-normativas"] ?? [], "fuente_normativa_id")}
        </select>
      </Field>
      <Field etiqueta="Responsable">
        <input className={inputClass} value={datos.responsable} onChange={(e) => set("responsable", e.target.value)} />
      </Field>
      <Field etiqueta="Nota mínima">
        <input
          className={inputClass}
          type="number"
          min={0}
          step="0.1"
          value={datos.nota_minima}
          onChange={(e) => set("nota_minima", e.target.value)}
        />
      </Field>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" checked={datos.es_tarea_critica} onChange={(e) => set("es_tarea_critica", e.target.checked)} />
        Tarea crítica (independiente de criticidad; alimenta el indicador de tareas críticas)
      </label>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" checked={datos.evaluacion} onChange={(e) => set("evaluacion", e.target.checked)} />
        Requiere evaluación
      </label>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" checked={datos.certificado} onChange={(e) => set("certificado", e.target.checked)} />
        Emite certificado
      </label>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input
          type="checkbox"
          checked={datos.requiere_listado_asistencia}
          onChange={(e) => set("requiere_listado_asistencia", e.target.checked)}
        />
        Requiere listado de asistencia
      </label>
      <div className="flex justify-end gap-2 sm:col-span-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Guardar capacitación</Button>
      </div>
    </form>
  );
}
