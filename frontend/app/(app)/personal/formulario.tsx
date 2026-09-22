"use client";

import { FormEvent, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { procesoRequiereProyecto } from "@/lib/catalogos";
import type { ApiErrorMap } from "@/lib/api";
import type {
  CargoCorporativo,
  PersonaCorporativa,
  ProcesoCronograma,
  TipoDocumentoCorporativo,
} from "@/lib/tipos";

export type DatosTrabajador = {
  numero_documento: string;
  nombre_completo: string;
  correo: string;
  cargo_id: string;
  proceso_id: string;
  proyecto: string;
  fecha_ingreso: string;
  tipo_documento_id: string;
};

export type ErroresTrabajador = Partial<Record<keyof DatosTrabajador, string>>;

function vacio(): DatosTrabajador {
  return {
    numero_documento: "",
    nombre_completo: "",
    correo: "",
    cargo_id: "",
    proceso_id: "",
    proyecto: "",
    fecha_ingreso: "",
    tipo_documento_id: "1",
  };
}

function desdeItem(item: PersonaCorporativa): DatosTrabajador {
  return {
    numero_documento: item.numero_documento,
    nombre_completo: item.nombre_completo,
    correo: item.correo_corporativo ?? "",
    cargo_id: item.cargo_id ? String(item.cargo_id) : "",
    proceso_id: item.proceso_id ? String(item.proceso_id) : "",
    proyecto: item.proyecto ?? "",
    fecha_ingreso: item.contrato_fecha_inicio ?? "",
    tipo_documento_id: item.tipo_documento_id ? String(item.tipo_documento_id) : "1",
  };
}

export function validarDatosTrabajador(
  datos: DatosTrabajador,
  procesos: ProcesoCronograma[],
  esEdicion = false,
): ErroresTrabajador {
  const errores: ErroresTrabajador = {};
  const correo = datos.correo.trim();

  if (!esEdicion) {
    const documento = datos.numero_documento.trim();
    const nombre = datos.nombre_completo.trim();

    if (!documento) {
      errores.numero_documento = "El documento es obligatorio.";
    }

    if (!nombre) {
      errores.nombre_completo = "El nombre es obligatorio.";
    } else if (nombre.split(/\s+/).length < 2) {
      errores.nombre_completo = "El nombre debe incluir al menos un nombre y un apellido.";
    }

    if (!datos.fecha_ingreso) {
      errores.fecha_ingreso = "La fecha de ingreso es obligatoria.";
    }
  }

  if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
    errores.correo = "El correo no tiene un formato válido.";
  }

  if (!datos.cargo_id) {
    errores.cargo_id = "El cargo es obligatorio.";
  }

  if (!datos.proceso_id) {
    errores.proceso_id = "El proceso es obligatorio.";
  } else if (procesoRequiereProyecto(datos.proceso_id, procesos) && !datos.proyecto.trim()) {
    errores.proyecto = "El proyecto es obligatorio para este proceso.";
  }

  return errores;
}

function primerError(mapa: ApiErrorMap | null, campo: string): string | undefined {
  const lista = mapa?.[campo];
  return lista && lista.length > 0 ? lista[0] : undefined;
}

export function FormularioTrabajador({
  inicial,
  cargos,
  procesos,
  proyectos,
  tiposDocumento,
  erroresApi,
  onCancelar,
  onGuardar,
}: {
  inicial: PersonaCorporativa | null;
  cargos: CargoCorporativo[];
  procesos: ProcesoCronograma[];
  proyectos: string[];
  tiposDocumento: TipoDocumentoCorporativo[];
  erroresApi: ApiErrorMap | null;
  onCancelar: () => void;
  onGuardar: (evento: FormEvent, datos: DatosTrabajador) => void;
}) {
  const [datos, setDatos] = useState<DatosTrabajador>(inicial ? desdeItem(inicial) : vacio());
  const [errores, setErrores] = useState<ErroresTrabajador>({});

  useEffect(() => {
    setDatos(inicial ? desdeItem(inicial) : vacio());
    setErrores({});
  }, [inicial]);

  function set<K extends keyof DatosTrabajador>(campo: K, valor: DatosTrabajador[K]) {
    setDatos((prev) => {
      const siguiente = { ...prev, [campo]: valor };
      if (campo === "proceso_id" && !procesoRequiereProyecto(String(valor), procesos)) {
        siguiente.proyecto = "";
      }
      return siguiente;
    });
  }

  const esEdicion = inicial !== null;
  const muestraProyecto = procesoRequiereProyecto(datos.proceso_id, procesos);

  function enviar(evento: FormEvent) {
    const locales = validarDatosTrabajador(datos, procesos, esEdicion);
    setErrores(locales);
    if (Object.keys(locales).length > 0) {
      evento.preventDefault();
      return;
    }
    onGuardar(evento, datos);
  }

  return (
    <form className="grid gap-4 sm:grid-cols-2" onSubmit={enviar}>
      {esEdicion ? (
        <p className="sm:col-span-2 text-sm text-slate-600">
          Puede modificar correo, cargo, proceso HSEQ y proyecto (si el proceso lo exige). Documento y
          nombre no se cambian aquí.
        </p>
      ) : null}
      <Field
        etiqueta="Documento"
        error={errores.numero_documento ?? primerError(erroresApi, "numero_documento")}
      >
        <input
          className={inputClass}
          value={datos.numero_documento}
          onChange={(e) => set("numero_documento", e.target.value)}
          maxLength={15}
          required={!esEdicion}
          disabled={esEdicion}
        />
      </Field>
      <Field etiqueta="Tipo de documento">
        <select
          className={inputClass}
          value={datos.tipo_documento_id}
          onChange={(e) => set("tipo_documento_id", e.target.value)}
          disabled={esEdicion}
        >
          {tiposDocumento.length === 0 ? <option value="1">CC</option> : null}
          {tiposDocumento.map((tipo) => (
            <option key={tipo.tipo_documento_id} value={tipo.tipo_documento_id}>
              {tipo.abreviatura} — {tipo.descripcion}
            </option>
          ))}
        </select>
      </Field>
      <Field
        etiqueta="Nombre completo"
        error={errores.nombre_completo ?? primerError(erroresApi, "nombre_completo")}
      >
        <input
          className={inputClass}
          value={datos.nombre_completo}
          onChange={(e) => set("nombre_completo", e.target.value)}
          required={!esEdicion}
          disabled={esEdicion}
        />
      </Field>
      <Field etiqueta="Correo" error={errores.correo ?? primerError(erroresApi, "correo")}>
        <input
          className={inputClass}
          type="email"
          value={datos.correo}
          onChange={(e) => set("correo", e.target.value)}
        />
      </Field>
      <Field etiqueta="Cargo" error={errores.cargo_id ?? primerError(erroresApi, "cargo_id")}>
        <select
          className={inputClass}
          value={datos.cargo_id}
          onChange={(e) => set("cargo_id", e.target.value)}
          required
        >
          <option value="">Seleccione un cargo</option>
          {cargos.map((cargo) => (
            <option key={cargo.cargo_id} value={cargo.cargo_id}>
              {cargo.nombre_cargo}
            </option>
          ))}
        </select>
      </Field>
      <Field etiqueta="Proceso" error={errores.proceso_id ?? primerError(erroresApi, "proceso_id")}>
        <select
          className={inputClass}
          value={datos.proceso_id}
          onChange={(e) => set("proceso_id", e.target.value)}
          required
        >
          <option value="">Seleccione un proceso</option>
          {procesos.map((proceso) => (
            <option key={proceso.proceso_id} value={proceso.proceso_id}>
              {proceso.nombre}
            </option>
          ))}
        </select>
      </Field>
      {muestraProyecto ? (
        <Field etiqueta="Proyecto" error={errores.proyecto ?? primerError(erroresApi, "proyecto")}>
          <select
            className={inputClass}
            value={datos.proyecto}
            onChange={(e) => set("proyecto", e.target.value)}
            required
          >
            <option value="">Seleccione un proyecto</option>
            {proyectos.map((proyecto) => (
              <option key={proyecto} value={proyecto}>
                {proyecto}
              </option>
            ))}
          </select>
        </Field>
      ) : null}
      <Field
        etiqueta="Fecha de ingreso"
        error={errores.fecha_ingreso ?? primerError(erroresApi, "fecha_ingreso")}
      >
        <input
          className={inputClass}
          type="date"
          value={datos.fecha_ingreso}
          onChange={(e) => set("fecha_ingreso", e.target.value)}
          required={!esEdicion}
          disabled={esEdicion}
        />
      </Field>
      <div className="flex justify-end gap-2 sm:col-span-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Guardar cambios</Button>
      </div>
    </form>
  );
}
