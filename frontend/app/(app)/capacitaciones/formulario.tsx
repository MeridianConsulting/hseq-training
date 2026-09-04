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
  tipo_capacitacion_id: string;
  duracion_estimada_horas: string;
  vigencia_id: string;
  modalidad_default_id: string;
  es_tarea_critica: boolean;
  evaluacion: boolean;
  nota_minima: string;
  certificado: boolean;
  requiere_listado_asistencia: boolean;
  estado: "ACTIVA" | "INACTIVA";
};

export type ErroresCapacitacion = Partial<Record<keyof DatosCapacitacion, string>>;

function vacio(): DatosCapacitacion {
  return {
    codigo: "",
    nombre: "",
    objetivo: "",
    tipo_capacitacion_id: "",
    duracion_estimada_horas: "",
    vigencia_id: "",
    modalidad_default_id: "",
    es_tarea_critica: false,
    evaluacion: false,
    nota_minima: "",
    certificado: false,
    requiere_listado_asistencia: false,
    estado: "ACTIVA",
  };
}

function desdeItem(item: Capacitacion): DatosCapacitacion {
  return {
    codigo: item.codigo,
    nombre: item.nombre,
    objetivo: item.objetivo,
    tipo_capacitacion_id: item.tipo_capacitacion_id ? String(item.tipo_capacitacion_id) : "",
    duracion_estimada_horas: item.duracion_estimada_horas != null ? String(item.duracion_estimada_horas) : "",
    vigencia_id: item.vigencia_id ? String(item.vigencia_id) : "",
    modalidad_default_id: item.modalidad_default_id ? String(item.modalidad_default_id) : "",
    es_tarea_critica: item.es_tarea_critica,
    evaluacion: item.evaluacion,
    nota_minima: item.evaluacion && item.nota_minima != null ? String(item.nota_minima) : "",
    certificado: item.certificado,
    requiere_listado_asistencia: item.requiere_listado_asistencia,
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
    errores.duracion_estimada_horas = "La duración es obligatoria.";
  } else if (!Number.isFinite(Number(horas))) {
    errores.duracion_estimada_horas = "La duración debe ser un valor numérico.";
  } else if (Number(horas) <= 0) {
    errores.duracion_estimada_horas = "La duración debe ser mayor que cero.";
  }

  if (!datos.tipo_capacitacion_id) {
    errores.tipo_capacitacion_id = "El tipo de capacitación es obligatorio.";
  }
  if (!datos.modalidad_default_id) {
    errores.modalidad_default_id = "La modalidad es obligatoria.";
  }

  if (datos.evaluacion) {
    const nota = datos.nota_minima.trim();
    if (nota === "") {
      errores.nota_minima = "La nota mínima es obligatoria cuando la capacitación requiere evaluación.";
    } else if (!Number.isFinite(Number(nota))) {
      errores.nota_minima = "La nota mínima debe ser un valor numérico.";
    } else if (Number(nota) < 0) {
      errores.nota_minima = "La nota mínima no puede ser negativa.";
    }
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
  const [errores, setErrores] = useState<ErroresCapacitacion>({});

  const catalogosVisibles = useMemo(() => {
    if (!inicial) {
      return catalogos;
    }

    return {
      ...catalogos,
      "tipos-capacitacion": conValorHistorico(
        catalogos["tipos-capacitacion"] ?? [],
        "tipo_capacitacion_id",
        inicial.tipo_capacitacion_id,
        inicial.tipo_nombre,
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
    };
  }, [catalogos, inicial]);

  useEffect(() => {
    if (!erroresApi) {
      return;
    }
    setErrores({
      codigo: primerError(erroresApi, "codigo"),
      nombre: primerError(erroresApi, "nombre"),
      objetivo: primerError(erroresApi, "objetivo"),
      duracion_estimada_horas: primerError(erroresApi, "duracion_estimada_horas"),
      tipo_capacitacion_id: primerError(erroresApi, "tipo_capacitacion_id"),
      modalidad_default_id: primerError(erroresApi, "modalidad_default_id"),
      vigencia_id: primerError(erroresApi, "vigencia_id"),
      nota_minima: primerError(erroresApi, "nota_minima"),
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
      <p className="sm:col-span-2 text-sm font-semibold text-slate-800">Información básica</p>
      <Field etiqueta="Código" error={errores.codigo}>
        <input className={inputClass} required value={datos.codigo} onChange={(e) => set("codigo", e.target.value)} />
      </Field>
      <Field etiqueta="Nombre" error={errores.nombre}>
        <input className={inputClass} required value={datos.nombre} onChange={(e) => set("nombre", e.target.value)} />
      </Field>
      <div className="sm:col-span-2">
        <Field etiqueta="Objetivo" error={errores.objetivo}>
          <textarea
            className={inputClass}
            required
            rows={3}
            value={datos.objetivo}
            onChange={(e) => set("objetivo", e.target.value)}
          />
        </Field>
      </div>
      <Field etiqueta="Duración (horas)" error={errores.duracion_estimada_horas}>
        <input
          className={inputClass}
          type="number"
          min={0.01}
          step="0.5"
          value={datos.duracion_estimada_horas}
          onChange={(e) => set("duracion_estimada_horas", e.target.value)}
        />
      </Field>

      <p className="sm:col-span-2 mt-2 text-sm font-semibold text-slate-800">Configuración</p>
      <Field etiqueta="Tipo / clasificación" error={errores.tipo_capacitacion_id}>
        <select
          className={inputClass}
          value={datos.tipo_capacitacion_id}
          onChange={(e) => set("tipo_capacitacion_id", e.target.value)}
        >
          <option value="">Seleccione…</option>
          {opciones(catalogosVisibles["tipos-capacitacion"] ?? [], "tipo_capacitacion_id")}
        </select>
      </Field>
      <Field etiqueta="Modalidad" error={errores.modalidad_default_id}>
        <select
          className={inputClass}
          value={datos.modalidad_default_id}
          onChange={(e) => set("modalidad_default_id", e.target.value)}
        >
          <option value="">Seleccione…</option>
          {opciones(catalogosVisibles.modalidades ?? [], "modalidad_id")}
        </select>
      </Field>
      <Field etiqueta="Vigencia" error={errores.vigencia_id}>
        <select className={inputClass} value={datos.vigencia_id} onChange={(e) => set("vigencia_id", e.target.value)}>
          <option value="">No vence</option>
          {opciones(catalogosVisibles.vigencias ?? [], "vigencia_id")}
        </select>
      </Field>
      <Field etiqueta="Tarea crítica">
        <select
          className={inputClass}
          value={datos.es_tarea_critica ? "1" : "0"}
          onChange={(e) => set("es_tarea_critica", e.target.value === "1")}
        >
          <option value="0">No</option>
          <option value="1">Sí</option>
        </select>
      </Field>
      <Field etiqueta="Requiere evaluación">
        <select
          className={inputClass}
          value={datos.evaluacion ? "1" : "0"}
          onChange={(e) => {
            const si = e.target.value === "1";
            setDatos((prev) => ({ ...prev, evaluacion: si, nota_minima: si ? prev.nota_minima : "" }));
            setErrores((prev) => ({ ...prev, evaluacion: undefined, nota_minima: undefined }));
          }}
        >
          <option value="0">No</option>
          <option value="1">Sí</option>
        </select>
      </Field>
      {datos.evaluacion ? (
        <Field etiqueta="Nota mínima" error={errores.nota_minima}>
          <input
            className={inputClass}
            type="number"
            min={0}
            step="0.1"
            value={datos.nota_minima}
            onChange={(e) => set("nota_minima", e.target.value)}
          />
        </Field>
      ) : null}
      <Field etiqueta="Requiere lista de asistencia">
        <select
          className={inputClass}
          value={datos.requiere_listado_asistencia ? "1" : "0"}
          onChange={(e) => set("requiere_listado_asistencia", e.target.value === "1")}
        >
          <option value="0">No</option>
          <option value="1">Sí</option>
        </select>
      </Field>
      <Field etiqueta="Requiere certificado">
        <select
          className={inputClass}
          value={datos.certificado ? "1" : "0"}
          onChange={(e) => set("certificado", e.target.value === "1")}
        >
          <option value="0">No</option>
          <option value="1">Sí</option>
        </select>
      </Field>
      {inicial ? (
        <Field etiqueta="Estado" error={errores.estado}>
          <select
            className={inputClass}
            value={datos.estado}
            onChange={(e) => set("estado", e.target.value as DatosCapacitacion["estado"])}
          >
            <option value="ACTIVA">Activa</option>
            <option value="INACTIVA">Inactiva</option>
          </select>
        </Field>
      ) : null}
      <div className="flex justify-end gap-2 sm:col-span-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Guardar capacitación</Button>
      </div>
    </form>
  );
}
