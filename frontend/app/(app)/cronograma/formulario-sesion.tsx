"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ApiErrorMap } from "@/lib/api";
import type {
  ContextoSesion,
  ConvocableSesion,
  DetalleSesion,
  ItemCatalogo,
  ItemCronograma,
  ParticipanteSesion,
  SesionCronograma,
} from "@/lib/tipos";

export type DatosSesion = {
  fecha: string;
  hora: string;
  modalidad_id: string;
  ubicacion_id: string;
  enlace_virtual: string;
  proveedor_id: string;
  cupo_maximo: string;
};

export type ErroresSesion = Partial<Record<keyof DatosSesion | "asignacion_ids", string>>;

function idCatalogo(item: ItemCatalogo, pk: string): string {
  return String(item[pk] ?? "");
}

function nombreCatalogo(item: ItemCatalogo): string {
  return String(item.nombre ?? "");
}

export function tipoModalidad(nombre: string | null | undefined): "VIRTUAL" | "PRESENCIAL" | "MIXTA" | "OTRA" {
  const clave = (nombre ?? "").toUpperCase();
  if (clave.includes("MIXTA")) return "MIXTA";
  if (clave.includes("VIRTUAL")) return "VIRTUAL";
  if (clave.includes("PRESENCIAL")) return "PRESENCIAL";
  return "OTRA";
}

function mesPadded(mes: number): string {
  return String(mes).padStart(2, "0");
}

function vacio(item: ItemCronograma): DatosSesion {
  return {
    fecha: `${item.anio}-${mesPadded(item.mes)}-01`,
    hora: "08:00",
    modalidad_id: "",
    ubicacion_id: "",
    enlace_virtual: "",
    proveedor_id: "",
    cupo_maximo: "",
  };
}

function desdeSesion(sesion: SesionCronograma): DatosSesion {
  return {
    fecha: sesion.fecha ?? "",
    hora: sesion.hora ?? "08:00",
    modalidad_id: String(sesion.modalidad_id),
    ubicacion_id: sesion.ubicacion_id ? String(sesion.ubicacion_id) : "",
    enlace_virtual: sesion.enlace_virtual ?? "",
    proveedor_id: sesion.proveedor_id ? String(sesion.proveedor_id) : "",
    cupo_maximo: String(sesion.cupo_maximo),
  };
}

function primerError(errores: ApiErrorMap | null, campo: string): string | undefined {
  return errores?.[campo]?.[0];
}

export function validarDatosSesion(
  datos: DatosSesion,
  tipo: ReturnType<typeof tipoModalidad>,
  seleccionados: number,
  cupoOcupado: number,
): ErroresSesion {
  const errores: ErroresSesion = {};
  if (!datos.fecha) {
    errores.fecha = "La fecha de la sesión es obligatoria.";
  }
  if (!datos.hora) {
    errores.hora = "La hora de la sesión es obligatoria.";
  }
  if (!datos.modalidad_id) {
    errores.modalidad_id = "La modalidad es obligatoria.";
  }
  if ((tipo === "PRESENCIAL" || tipo === "MIXTA") && !datos.ubicacion_id) {
    errores.ubicacion_id = "La ubicación es obligatoria.";
  }
  if ((tipo === "VIRTUAL" || tipo === "MIXTA") && !datos.enlace_virtual.trim()) {
    errores.enlace_virtual = "El enlace es obligatorio.";
  }
  if (datos.enlace_virtual.trim() && !/^https?:\/\//i.test(datos.enlace_virtual.trim())) {
    errores.enlace_virtual = "El enlace no tiene un formato válido.";
  }
  if (!datos.proveedor_id) {
    errores.proveedor_id = "El proveedor o capacitador es obligatorio.";
  }
  const cupo = Number(datos.cupo_maximo);
  if (!datos.cupo_maximo.trim()) {
    errores.cupo_maximo = "El cupo máximo es obligatorio.";
  } else if (!/^\d+$/.test(datos.cupo_maximo.trim()) || !Number.isInteger(cupo)) {
    errores.cupo_maximo = "El cupo máximo debe ser un número entero.";
  } else if (cupo < 1) {
    errores.cupo_maximo = "El cupo máximo debe ser mayor que cero.";
  } else if (cupo < cupoOcupado) {
    errores.cupo_maximo = `No puede ser menor que los ${cupoOcupado} convocados actuales.`;
  } else if (seleccionados > cupo - cupoOcupado) {
    errores.asignacion_ids = `El cupo máximo de la sesión es de ${cupo} trabajadores. No puede convocar más participantes.`;
  }
  return errores;
}

export function FormularioSesion({
  item,
  sesion,
  onCancelar,
  onGuardado,
}: {
  item: ItemCronograma;
  sesion?: SesionCronograma | null;
  onCancelar: () => void;
  onGuardado: () => void;
}) {
  const esEdicion = Boolean(sesion);
  const [datos, setDatos] = useState<DatosSesion>(sesion ? desdeSesion(sesion) : vacio(item));
  const [errores, setErrores] = useState<ErroresSesion>({});
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null);
  const [guardando, setGuardando] = useState(false);
  const [contexto, setContexto] = useState<ContextoSesion | null>(null);
  const [buscar, setBuscar] = useState("");
  const [seleccionados, setSeleccionados] = useState<number[]>([]);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    const abortado = { actual: false };
    void (async () => {
      setCargando(true);
      const ruta = sesion
        ? withQuery(`/api/sesiones/${sesion.sesion_id}/convocables`, { buscar: buscar.trim() || undefined })
        : withQuery("/api/sesiones/convocables", {
            plan_detalle_id: item.plan_detalle_id,
            buscar: buscar.trim() || undefined,
          });
      const r = await apiGet<ContextoSesion>(ruta);
      if (abortado.actual) return;
      if (r.success && r.data) {
        setContexto(r.data);
        if (!sesion) {
          setDatos((prev) => ({
            ...prev,
            modalidad_id: prev.modalidad_id || (r.data.modalidad_default_id ? String(r.data.modalidad_default_id) : ""),
            proveedor_id: prev.proveedor_id || (r.data.proveedor_default_id ? String(r.data.proveedor_default_id) : ""),
          }));
        }
      } else {
        setErrorGeneral(r.message || "No fue posible cargar los datos de la sesión.");
      }
      setCargando(false);
    })();
    return () => {
      abortado.actual = true;
    };
  }, [item.plan_detalle_id, sesion, buscar]);

  const tipo = useMemo(() => {
    const modalidad = (contexto?.modalidades ?? []).find(
      (m) => idCatalogo(m, "modalidad_id") === datos.modalidad_id,
    );
    return tipoModalidad(nombreCatalogo(modalidad ?? { nombre: "" }));
  }, [contexto, datos.modalidad_id]);

  const cupo = Number.parseInt(datos.cupo_maximo, 10);
  const cupoValido = Number.isInteger(cupo) && cupo > 0;
  const ocupados = sesion?.convocados ?? 0;
  const disponibles = cupoValido ? Math.max(0, cupo - ocupados - seleccionados.length) : 0;

  function set<K extends keyof DatosSesion>(clave: K, valor: DatosSesion[K]) {
    setDatos((prev) => ({ ...prev, [clave]: valor }));
    setErrores((prev) => ({ ...prev, [clave]: undefined }));
  }

  function toggle(asignacionId: number) {
    const tiene = seleccionados.includes(asignacionId);
    if (!tiene && cupoValido && ocupados + seleccionados.length >= cupo) {
      setErrores((prev) => ({
        ...prev,
        asignacion_ids: `El cupo máximo de la sesión es de ${cupo} trabajadores. No puede convocar más participantes.`,
      }));
      return;
    }
    setSeleccionados((prev) => (tiene ? prev.filter((id) => id !== asignacionId) : [...prev, asignacionId]));
    setErrores((prev) => ({ ...prev, asignacion_ids: undefined }));
  }

  async function enviar(evento: FormEvent) {
    evento.preventDefault();
    const locales = validarDatosSesion(datos, tipo, seleccionados.length, ocupados);
    setErrores(locales);
    if (Object.keys(locales).length > 0) {
      return;
    }
    setGuardando(true);
    setErrorGeneral(null);

    const cuerpo = {
      plan_detalle_id: item.plan_detalle_id,
      capacitacion_id: item.capacitacion_id,
      fecha: datos.fecha,
      hora: datos.hora,
      modalidad_id: Number(datos.modalidad_id),
      ubicacion_id: datos.ubicacion_id ? Number(datos.ubicacion_id) : null,
      enlace_virtual: tipo === "PRESENCIAL" ? null : datos.enlace_virtual.trim() || null,
      proveedor_id: Number(datos.proveedor_id),
      cupo_maximo: Number(datos.cupo_maximo),
      asignacion_ids: esEdicion ? undefined : seleccionados,
    };

    const r = esEdicion && sesion
      ? await apiPut<DetalleSesion>(`/api/sesiones/${sesion.sesion_id}`, cuerpo)
      : await apiPost<DetalleSesion>("/api/sesiones", cuerpo);

    setGuardando(false);
    if (!r.success) {
      setErrorGeneral(r.message || "No fue posible guardar la sesión.");
      setErrores({
        fecha: primerError(r.errors, "fecha"),
        hora: primerError(r.errors, "hora"),
        modalidad_id: primerError(r.errors, "modalidad_id"),
        ubicacion_id: primerError(r.errors, "ubicacion_id"),
        enlace_virtual: primerError(r.errors, "enlace_virtual"),
        proveedor_id: primerError(r.errors, "proveedor_id"),
        cupo_maximo: primerError(r.errors, "cupo_maximo"),
      });
      return;
    }
    onGuardado();
  }

  const convocables = contexto?.items ?? [];

  return (
    <form className="space-y-4" noValidate onSubmit={enviar}>
      <p className="text-sm text-slate-600">
        Plan {item.anio} · {item.codigo} — {item.tema}
      </p>

      {errorGeneral ? <Alert tono="error">{errorGeneral}</Alert> : null}

      <div className="grid gap-4 sm:grid-cols-2">
        <Field etiqueta="Fecha" error={errores.fecha}>
          <input
            className={inputClass}
            type="date"
            required
            value={datos.fecha}
            onChange={(e) => set("fecha", e.target.value)}
          />
        </Field>
        <Field etiqueta="Hora" error={errores.hora}>
          <input
            className={inputClass}
            type="time"
            required
            value={datos.hora}
            onChange={(e) => set("hora", e.target.value)}
          />
        </Field>
        <Field etiqueta="Modalidad" error={errores.modalidad_id}>
          <select
            className={inputClass}
            value={datos.modalidad_id}
            onChange={(e) => set("modalidad_id", e.target.value)}
          >
            <option value="">Seleccione</option>
            {(contexto?.modalidades ?? []).map((m) => (
              <option key={idCatalogo(m, "modalidad_id")} value={idCatalogo(m, "modalidad_id")}>
                {nombreCatalogo(m)}
              </option>
            ))}
          </select>
        </Field>
        {(tipo === "PRESENCIAL" || tipo === "MIXTA" || tipo === "OTRA") && (
          <Field etiqueta="Ubicación" error={errores.ubicacion_id}>
            <select
              className={inputClass}
              value={datos.ubicacion_id}
              onChange={(e) => set("ubicacion_id", e.target.value)}
            >
              <option value="">Seleccione</option>
              {(contexto?.ubicaciones ?? []).map((u) => (
                <option key={idCatalogo(u, "ubicacion_id")} value={idCatalogo(u, "ubicacion_id")}>
                  {nombreCatalogo(u)}
                </option>
              ))}
            </select>
          </Field>
        )}
        {(tipo === "VIRTUAL" || tipo === "MIXTA") && (
          <Field etiqueta="Enlace" error={errores.enlace_virtual}>
            <input
              className={inputClass}
              type="url"
              placeholder="https://"
              value={datos.enlace_virtual}
              onChange={(e) => set("enlace_virtual", e.target.value)}
            />
          </Field>
        )}
        <Field etiqueta="Proveedor / capacitador" error={errores.proveedor_id}>
          <select
            className={inputClass}
            value={datos.proveedor_id}
            onChange={(e) => set("proveedor_id", e.target.value)}
          >
            <option value="">Seleccione</option>
            {(contexto?.proveedores ?? []).map((p) => (
              <option key={idCatalogo(p, "proveedor_id")} value={idCatalogo(p, "proveedor_id")}>
                {nombreCatalogo(p)}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Cupo máximo" error={errores.cupo_maximo}>
          <input
            className={inputClass}
            type="number"
            min={1}
            step={1}
            inputMode="numeric"
            value={datos.cupo_maximo}
            onChange={(e) => set("cupo_maximo", e.target.value)}
          />
        </Field>
      </div>

      {!esEdicion ? (
        <>
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="font-medium text-slate-700">
              Cupo máximo: {cupoValido ? cupo : "—"}
            </span>
            <span className="text-slate-400">·</span>
            <span>Convocados: {seleccionados.length}</span>
            <span className="text-slate-400">·</span>
            <span>Disponibles: {cupoValido ? disponibles : "—"}</span>
            {cupoValido && seleccionados.length >= cupo ? (
              <Badge tono="aviso">Cupo completo</Badge>
            ) : null}
          </div>
          {errores.asignacion_ids ? <p className="text-xs text-red-600">{errores.asignacion_ids}</p> : null}
          <Field etiqueta="Trabajadores convocados">
            <input
              className={inputClass}
              value={buscar}
              onChange={(e) => setBuscar(e.target.value)}
              placeholder="Buscar por documento o nombre"
            />
          </Field>
          <div className="max-h-56 overflow-y-auto rounded-lg border border-slate-200">
            {cargando ? (
              <p className="px-3 py-4 text-sm text-slate-500">Cargando trabajadores…</p>
            ) : convocables.length === 0 ? (
              <p className="px-3 py-4 text-sm text-slate-500">
                No hay trabajadores con esta capacitación asignada.
              </p>
            ) : (
              convocables.map((persona) => (
                <FilaConvocable
                  key={persona.asignacion_id}
                  persona={persona}
                  marcado={seleccionados.includes(persona.asignacion_id)}
                  deshabilitado={
                    !seleccionados.includes(persona.asignacion_id) &&
                    cupoValido &&
                    ocupados + seleccionados.length >= cupo
                  }
                  onToggle={() => toggle(persona.asignacion_id)}
                />
              ))
            )}
          </div>
        </>
      ) : null}

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variante="secondary" onClick={onCancelar} disabled={guardando}>
          Cancelar
        </Button>
        <Button type="submit" disabled={guardando}>
          {esEdicion ? "Guardar cambios" : "Crear sesión"}
        </Button>
      </div>
    </form>
  );
}

function FilaConvocable({
  persona,
  marcado,
  deshabilitado,
  onToggle,
}: {
  persona: ConvocableSesion;
  marcado: boolean;
  deshabilitado: boolean;
  onToggle: () => void;
}) {
  return (
    <label
      className={`flex items-center gap-2 px-3 py-2 text-sm ${
        deshabilitado ? "cursor-not-allowed text-slate-400" : "cursor-pointer hover:bg-hseq-50"
      } ${marcado ? "bg-hseq-50" : ""}`}
    >
      <input type="checkbox" checked={marcado} disabled={deshabilitado} onChange={onToggle} />
      <span>
        {persona.persona_nombre}
        <span className="ml-2 text-xs text-slate-500">{persona.numero_documento}</span>
        {persona.en_plan ? (
          <span className="ml-2 text-xs font-medium text-hseq-700">En el plan</span>
        ) : null}
      </span>
    </label>
  );
}

export function PanelConvocados({
  sesionId,
  onCambio,
}: {
  sesionId: number;
  onCambio: () => void;
}) {
  const [detalle, setDetalle] = useState<DetalleSesion | null>(null);
  const [convocables, setConvocables] = useState<ConvocableSesion[]>([]);
  const [seleccionados, setSeleccionados] = useState<number[]>([]);
  const [buscar, setBuscar] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);

  async function recargar() {
    setCargando(true);
    setError(null);
    const [rSesion, rConv] = await Promise.all([
      apiGet<DetalleSesion>(`/api/sesiones/${sesionId}`),
      apiGet<ContextoSesion>(
        withQuery(`/api/sesiones/${sesionId}/convocables`, { buscar: buscar.trim() || undefined }),
      ),
    ]);
    if (rSesion.success && rSesion.data) {
      setDetalle(rSesion.data);
    } else {
      setError(rSesion.message || "No fue posible cargar la sesión.");
    }
    if (rConv.success && rConv.data) {
      setConvocables(rConv.data.items);
    }
    setCargando(false);
  }

  useEffect(() => {
    void recargar();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sesionId, buscar]);

  const cupo = detalle?.cupo_maximo ?? 0;
  const ocupados = detalle?.convocados ?? 0;
  const disponibles = detalle?.disponibles ?? 0;
  const lleno = Boolean(detalle?.cupo_completo);

  function toggle(asignacionId: number) {
    const tiene = seleccionados.includes(asignacionId);
    if (!tiene && ocupados + seleccionados.length >= cupo) {
      setError(`La sesión ya alcanzó el cupo máximo de ${cupo} trabajadores.`);
      return;
    }
    setError(null);
    setSeleccionados((prev) => (tiene ? prev.filter((id) => id !== asignacionId) : [...prev, asignacionId]));
  }

  async function agregar() {
    if (seleccionados.length < 1) {
      setError("Seleccione al menos un trabajador.");
      return;
    }
    if (ocupados + seleccionados.length > cupo) {
      setError(
        disponibles === 0
          ? `La sesión ya alcanzó el cupo máximo de ${cupo} trabajadores.`
          : `Solo hay ${disponibles} cupos disponibles.`,
      );
      return;
    }
    setGuardando(true);
    setError(null);
    const r = await apiPost<DetalleSesion>(`/api/sesiones/${sesionId}/participantes`, {
      asignacion_ids: seleccionados,
    });
    setGuardando(false);
    if (!r.success) {
      setError(r.message || "No fue posible convocar a los trabajadores.");
      return;
    }
    setSeleccionados([]);
    setMensaje(
      r.data?.cupo_completo
        ? `Convocados: ${r.data.convocados} / ${r.data.cupo_maximo}. Cupo completo.`
        : `Convocados: ${r.data?.convocados} / ${r.data?.cupo_maximo}.`,
    );
    await recargar();
    onCambio();
  }

  async function retirar(asignacionId: number) {
    setGuardando(true);
    setError(null);
    const r = await apiDelete<DetalleSesion>(`/api/sesiones/${sesionId}/participantes/${asignacionId}`);
    setGuardando(false);
    if (!r.success) {
      setError(r.message || "No fue posible retirar al trabajador.");
      return;
    }
    setMensaje(`Convocados: ${r.data?.convocados} / ${r.data?.cupo_maximo}.`);
    await recargar();
    onCambio();
  }

  const participantes: ParticipanteSesion[] = detalle?.participantes ?? [];

  return (
    <div className="space-y-4">
      {detalle ? (
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="font-medium text-slate-700">Cupo máximo: {cupo}</span>
          <span className="text-slate-400">·</span>
          <span>
            Convocados: {ocupados} / {cupo}
          </span>
          <span className="text-slate-400">·</span>
          <span>Disponibles: {disponibles}</span>
          {lleno ? <Badge tono="aviso">Cupo completo</Badge> : <Badge tono="ok">Cupos disponibles</Badge>}
        </div>
      ) : null}

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <div>
        <p className="mb-2 text-sm font-medium text-slate-700">Convocados</p>
        <div className="max-h-48 overflow-y-auto rounded-lg border border-slate-200">
          {cargando && !detalle ? (
            <p className="px-3 py-4 text-sm text-slate-500">Cargando…</p>
          ) : participantes.length === 0 ? (
            <p className="px-3 py-4 text-sm text-slate-500">Aún no hay trabajadores convocados.</p>
          ) : (
            participantes.map((p) => (
              <div key={p.asignacion_id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                <span>
                  {p.persona_nombre}
                  <span className="ml-2 text-xs text-slate-500">{p.numero_documento}</span>
                </span>
                <Button
                  type="button"
                  variante="ghost"
                  className="px-2 py-1 text-xs"
                  disabled={guardando}
                  onClick={() => void retirar(p.asignacion_id)}
                >
                  Retirar
                </Button>
              </div>
            ))
          )}
        </div>
      </div>

      <div>
        <p className="mb-2 text-sm font-medium text-slate-700">Agregar trabajadores</p>
        <input
          className={`${inputClass} mb-2`}
          value={buscar}
          onChange={(e) => setBuscar(e.target.value)}
          placeholder="Buscar por documento o nombre"
        />
        <div className="max-h-48 overflow-y-auto rounded-lg border border-slate-200">
          {convocables.length === 0 ? (
            <p className="px-3 py-4 text-sm text-slate-500">No hay más trabajadores disponibles para convocar.</p>
          ) : (
            convocables.map((persona) => (
              <FilaConvocable
                key={persona.asignacion_id}
                persona={persona}
                marcado={seleccionados.includes(persona.asignacion_id)}
                deshabilitado={
                  lleno && !seleccionados.includes(persona.asignacion_id)
                }
                onToggle={() => toggle(persona.asignacion_id)}
              />
            ))
          )}
        </div>
        <div className="mt-3 flex justify-end">
          <Button type="button" disabled={guardando || lleno || seleccionados.length < 1} onClick={() => void agregar()}>
            Convocar seleccionados
          </Button>
        </div>
      </div>
    </div>
  );
}
