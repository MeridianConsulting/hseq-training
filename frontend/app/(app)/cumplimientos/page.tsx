"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { ListaEvidencias } from "@/app/(app)/cumplimientos/evidencias";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { useDebouncedCallback, useFiltrosUrl } from "@/hooks/useFiltrosUrl";
import { ChevronDown, Eye, UserRound } from "lucide-react";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";
import type {
  ConsultaCumplimiento,
  DetalleConsultaCumplimiento,
  OpcionesConsultaCumplimientos,
  SituacionTrabajadorCumplimiento,
} from "@/lib/tipos";

const FILTROS_DEFAULT = {
  buscar: "",
  capacitacion_id: "",
  estado: "",
  cargo_id: "",
  proceso_id: "",
  proyecto: "",
  tipo_capacitacion_id: "",
  es_tarea_critica: "",
  estado_laboral: "Activo",
  fecha_realizacion_desde: "",
  fecha_realizacion_hasta: "",
  fecha_vencimiento_desde: "",
  fecha_vencimiento_hasta: "",
};

const ETIQUETAS_ESTADO: Record<string, string> = {
  PENDIENTE: "Pendiente",
  PENDIENTE_PROXIMA_A_VENCER: "Próxima a vencer",
  PENDIENTE_VENCIDA: "Pendiente vencida",
  COMPLETADA: "Cumple",
  PROXIMA_A_VENCER: "Próxima a vencer",
  VENCIDA: "Vencida",
};

function tonoEstado(estado: string): "alto" | "aviso" | "ok" | "neutral" {
  if (estado === "VENCIDA" || estado === "PENDIENTE_VENCIDA") return "alto";
  if (estado === "PROXIMA_A_VENCER" || estado === "PENDIENTE_PROXIMA_A_VENCER") return "aviso";
  if (estado === "COMPLETADA") return "ok";
  return "neutral";
}

function formatoFecha(valor: string | null | undefined): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function contextoPersona(item: ConsultaCumplimiento): string {
  const cargo = (item.cargo ?? "").trim();
  const proyecto = (item.proyecto ?? "").trim();
  if (cargo && proyecto) return `${cargo} · ${proyecto}`;
  return cargo || proyecto || "—";
}

function etiquetaAsistencia(valor: string | null): string {
  if (valor === "ASISTIO") return "Asistió";
  if (valor === "TARDE") return "Tarde";
  if (valor === "AUSENTE") return "Ausente";
  if (valor === "CONVOCADO") return "Convocado";
  return valor || "—";
}

function siNo(valor: boolean | null | undefined): string {
  if (valor === true) return "Sí";
  if (valor === false) return "No";
  return "—";
}

export default function Page() {
  return (
    <RequierePermiso permiso="cumplimientos.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { valores, setFiltro, limpiar } = useFiltrosUrl(FILTROS_DEFAULT, {
    keysDebounce: ["buscar"],
  });
  const [items, setItems] = useState<ConsultaCumplimiento[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [capacitaciones, setCapacitaciones] = useState<
    { capacitacion_id: number; codigo: string; nombre: string }[]
  >([]);
  const [opciones, setOpciones] = useState<OpcionesConsultaCumplimientos>({
    procesos: [],
    proyectos: [],
    cargos: [],
    tipos: [],
    capacitaciones: [],
  });
  const [masFiltros, setMasFiltros] = useState(() =>
    Boolean(
      valores.cargo_id ||
        valores.proceso_id ||
        valores.proyecto ||
        valores.tipo_capacitacion_id ||
        valores.es_tarea_critica ||
        valores.fecha_realizacion_desde ||
        valores.fecha_realizacion_hasta ||
        valores.fecha_vencimiento_desde ||
        valores.fecha_vencimiento_hasta,
    ),
  );
  const [detalle, setDetalle] = useState<DetalleConsultaCumplimiento | null>(null);
  const [situacion, setSituacion] = useState<SituacionTrabajadorCumplimiento | null>(null);

  async function cargarListado(paginaActual = 1) {
    setCargando(true);
    try {
      const respuesta = await apiGet<ListaPaginada<ConsultaCumplimiento>>(
        withQuery("/api/cumplimientos/consulta", {
          page: paginaActual,
          per_page: 15,
          buscar: valores.buscar.trim() || undefined,
          capacitacion_id: valores.capacitacion_id || undefined,
          estado: valores.estado || undefined,
          cargo_id: valores.cargo_id || undefined,
          proceso_id: valores.proceso_id || undefined,
          proyecto: valores.proyecto || undefined,
          tipo_capacitacion_id: valores.tipo_capacitacion_id || undefined,
          es_tarea_critica: valores.es_tarea_critica || undefined,
          estado_laboral: valores.estado_laboral || "todos",
          fecha_realizacion_desde: valores.fecha_realizacion_desde || undefined,
          fecha_realizacion_hasta: valores.fecha_realizacion_hasta || undefined,
          fecha_vencimiento_desde: valores.fecha_vencimiento_desde || undefined,
          fecha_vencimiento_hasta: valores.fecha_vencimiento_hasta || undefined,
        }),
      );
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar los cumplimientos.");
        return;
      }
      setItems(respuesta.data.items);
      setPagina(respuesta.data.pagination.current_page);
      setUltima(respuesta.data.pagination.last_page);
      setError(null);
    } finally {
      setCargando(false);
    }
  }

  useDebouncedCallback(
    () => {
      void cargarListado(1);
    },
    [
      valores.buscar,
      valores.capacitacion_id,
      valores.estado,
      valores.cargo_id,
      valores.proceso_id,
      valores.proyecto,
      valores.tipo_capacitacion_id,
      valores.es_tarea_critica,
      valores.estado_laboral,
      valores.fecha_realizacion_desde,
      valores.fecha_realizacion_hasta,
      valores.fecha_vencimiento_desde,
      valores.fecha_vencimiento_hasta,
    ],
  );

  useEffect(() => {
    void (async () => {
      const opts = await apiGet<OpcionesConsultaCumplimientos>("/api/cumplimientos/consulta/opciones");
      if (opts.success && opts.data) {
        setOpciones(opts.data);
        setCapacitaciones(opts.data.capacitaciones ?? []);
      }
    })();
  }, []);

  const extrasActivos = [
    valores.cargo_id,
    valores.proceso_id,
    valores.proyecto,
    valores.tipo_capacitacion_id,
    valores.es_tarea_critica,
    valores.fecha_realizacion_desde,
    valores.fecha_realizacion_hasta,
    valores.fecha_vencimiento_desde,
    valores.fecha_vencimiento_hasta,
    valores.estado_laboral !== "Activo" ? valores.estado_laboral : "",
  ].filter(Boolean).length;

  const chipsActivos = useMemo(() => {
    const chips: ChipFiltro[] = [];
    if (valores.buscar.trim()) {
      chips.push({ clave: "buscar", etiqueta: "Trabajador", valor: valores.buscar.trim() });
    }
    if (valores.capacitacion_id) {
      const cap = capacitaciones.find((c) => String(c.capacitacion_id) === valores.capacitacion_id);
      chips.push({
        clave: "capacitacion_id",
        etiqueta: "Capacitación",
        valor: cap ? `${cap.codigo} — ${cap.nombre}` : valores.capacitacion_id,
      });
    }
    if (valores.estado) {
      chips.push({
        clave: "estado",
        etiqueta: "Estado",
        valor: ETIQUETAS_ESTADO[valores.estado] ?? valores.estado,
      });
    }
    if (valores.cargo_id) {
      const cargo = opciones.cargos.find((c) => String(c.cargo_id) === valores.cargo_id);
      chips.push({ clave: "cargo_id", etiqueta: "Cargo", valor: cargo?.nombre_cargo ?? valores.cargo_id });
    }
    if (valores.proceso_id) {
      const proc = opciones.procesos.find((p) => String(p.proceso_id) === valores.proceso_id);
      chips.push({ clave: "proceso_id", etiqueta: "Proceso", valor: proc?.nombre ?? valores.proceso_id });
    }
    if (valores.proyecto) {
      chips.push({ clave: "proyecto", etiqueta: "Proyecto", valor: valores.proyecto });
    }
    if (valores.tipo_capacitacion_id) {
      const tipo = opciones.tipos.find((t) => String(t.tipo_capacitacion_id) === valores.tipo_capacitacion_id);
      chips.push({ clave: "tipo_capacitacion_id", etiqueta: "Tipo", valor: tipo?.nombre ?? valores.tipo_capacitacion_id });
    }
    if (valores.es_tarea_critica) {
      chips.push({ clave: "es_tarea_critica", etiqueta: "Tarea crítica", valor: "Sí" });
    }
    if (valores.estado_laboral && valores.estado_laboral !== "Activo") {
      chips.push({
        clave: "estado_laboral",
        etiqueta: "Estado laboral",
        valor: valores.estado_laboral === "todos" ? "Todos" : valores.estado_laboral,
      });
    }
    return chips;
  }, [valores, capacitaciones, opciones]);

  async function verDetalle(item: ConsultaCumplimiento) {
    const respuesta = await apiGet<DetalleConsultaCumplimiento>(
      `/api/cumplimientos/consulta/${item.asignacion_id}`,
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible consultar el detalle.");
      return;
    }
    setDetalle(respuesta.data);
  }

  async function verSituacion(personaId: number) {
    const respuesta = await apiGet<SituacionTrabajadorCumplimiento>(
      `/api/cumplimientos/consulta/trabajador/${personaId}`,
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible consultar al trabajador.");
      return;
    }
    setSituacion(respuesta.data);
    setError(null);
  }

  return (
    <>
      <PageHeader
        titulo="Cumplimientos"
        descripcion="Consulte el estado real de las capacitaciones que debe cumplir cada trabajador. El registro de asistencia, evaluación y evidencias se hace en el Tablero de Cronograma."
      />

      {error ? <Alert tono="error">{error}</Alert> : null}

      {situacion ? (
        <Card className="mb-6">
          <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-sm font-semibold text-hseq-900">
                {situacion.trabajador.nombre ?? `Persona ${situacion.trabajador.persona_id_ext}`}
              </h2>
              <p className="text-sm text-slate-600">
                Cargo: {situacion.trabajador.cargo ?? "—"}
                {situacion.trabajador.proyecto ? ` · Proyecto: ${situacion.trabajador.proyecto}` : ""}
                {` · Estado corporativo: ${situacion.trabajador.estado_laboral ?? "—"}`}
                {situacion.trabajador.documento ? ` · ${situacion.trabajador.documento}` : ""}
              </p>
              <Link
                href={`/personal/${situacion.trabajador.persona_id_ext}`}
                className="text-sm text-hseq-800 underline"
              >
                Ver ficha en Personal
              </Link>
            </div>
            <Button type="button" variante="secondary" onClick={() => setSituacion(null)}>
              Cerrar
            </Button>
          </div>
          <Table
            columnas={[
              { clave: "cap", etiqueta: "Capacitación" },
              { clave: "real", etiqueta: "Realizada" },
              { clave: "vence", etiqueta: "Vencimiento" },
              { clave: "estado", etiqueta: "Estado" },
            ]}
            filas={situacion.items.map((item) => [
              `${item.capacitacion_codigo ?? ""} — ${item.capacitacion_nombre ?? ""}`,
              formatoFecha(item.fecha_realizacion),
              formatoFecha(item.fecha_vencimiento),
              <Badge key="e" tono={tonoEstado(item.estado_calculado)}>
                {ETIQUETAS_ESTADO[item.estado_calculado] ?? item.estado_calculado}
              </Badge>,
            ])}
            vacio="Este trabajador no tiene capacitaciones asignadas."
          />
        </Card>
      ) : null}

      <Filters>
        <Field etiqueta="Trabajador">
          <input
            className={inputClass}
            value={valores.buscar}
            onChange={(e) => setFiltro("buscar", e.target.value)}
            placeholder="Nombre, documento o capacitación"
          />
        </Field>
        <Field etiqueta="Capacitación">
          <select
            className={inputClass}
            value={valores.capacitacion_id}
            onChange={(e) => setFiltro("capacitacion_id", e.target.value)}
          >
            <option value="">Todas</option>
            {capacitaciones.map((cap) => (
              <option key={cap.capacitacion_id} value={cap.capacitacion_id}>
                {cap.codigo} — {cap.nombre}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={valores.estado}
            onChange={(e) => setFiltro("estado", e.target.value)}
          >
            <option value="">Todos</option>
            <option value="COMPLETADA">Cumple</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="PENDIENTE_PROXIMA_A_VENCER">Próxima a vencer (plazo)</option>
            <option value="PENDIENTE_VENCIDA">Pendiente vencida</option>
            <option value="PROXIMA_A_VENCER">Vigencia próxima a vencer</option>
            <option value="VENCIDA">Vencida</option>
          </select>
        </Field>
        <Field etiqueta="Estado laboral">
          <select
            className={inputClass}
            value={valores.estado_laboral}
            onChange={(e) => setFiltro("estado_laboral", e.target.value)}
          >
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
            <option value="todos">Todos</option>
          </select>
        </Field>
      </Filters>

      <div className="mb-4">
        <button
          type="button"
          className="inline-flex items-center gap-1.5 text-sm font-semibold text-hseq-700 hover:text-hseq-800"
          aria-expanded={masFiltros}
          onClick={() => setMasFiltros((abierto) => !abierto)}
        >
          <ChevronDown
            className={`h-4 w-4 transition-transform ${masFiltros ? "rotate-180" : ""}`}
            aria-hidden
          />
          {masFiltros ? "Menos filtros" : "Más filtros"}
          {!masFiltros && extrasActivos > 0 ? (
            <span className="rounded-full bg-hseq-100 px-1.5 text-xs font-medium text-hseq-800">
              {extrasActivos}
            </span>
          ) : null}
        </button>
        {masFiltros ? (
          <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Field etiqueta="Cargo">
              <select
                className={inputClass}
                value={valores.cargo_id}
                onChange={(e) => setFiltro("cargo_id", e.target.value)}
              >
                <option value="">Todos</option>
                {opciones.cargos.map((c) => (
                  <option key={c.cargo_id} value={c.cargo_id}>
                    {c.nombre_cargo}
                  </option>
                ))}
              </select>
            </Field>
            <Field etiqueta="Proceso">
              <select
                className={inputClass}
                value={valores.proceso_id}
                onChange={(e) => setFiltro("proceso_id", e.target.value)}
              >
                <option value="">Todos</option>
                {opciones.procesos.map((p) => (
                  <option key={p.proceso_id} value={p.proceso_id}>
                    {p.nombre}
                  </option>
                ))}
              </select>
            </Field>
            <Field etiqueta="Proyecto">
              <select
                className={inputClass}
                value={valores.proyecto}
                onChange={(e) => setFiltro("proyecto", e.target.value)}
              >
                <option value="">Todos</option>
                {opciones.proyectos.map((nombre) => (
                  <option key={nombre} value={nombre}>
                    {nombre}
                  </option>
                ))}
              </select>
            </Field>
            <Field etiqueta="Tipo">
              <select
                className={inputClass}
                value={valores.tipo_capacitacion_id}
                onChange={(e) => setFiltro("tipo_capacitacion_id", e.target.value)}
              >
                <option value="">Todos</option>
                {opciones.tipos.map((t) => (
                  <option key={t.tipo_capacitacion_id} value={t.tipo_capacitacion_id}>
                    {t.nombre}
                  </option>
                ))}
              </select>
            </Field>
            <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={valores.es_tarea_critica === "1"}
                onChange={(e) => setFiltro("es_tarea_critica", e.target.checked ? "1" : "")}
              />
              Solo tareas críticas
            </label>
            <Field etiqueta="Realización desde">
              <input
                type="date"
                className={inputClass}
                value={valores.fecha_realizacion_desde}
                onChange={(e) => setFiltro("fecha_realizacion_desde", e.target.value)}
              />
            </Field>
            <Field etiqueta="Realización hasta">
              <input
                type="date"
                className={inputClass}
                value={valores.fecha_realizacion_hasta}
                onChange={(e) => setFiltro("fecha_realizacion_hasta", e.target.value)}
              />
            </Field>
            <Field etiqueta="Vencimiento desde">
              <input
                type="date"
                className={inputClass}
                value={valores.fecha_vencimiento_desde}
                onChange={(e) => setFiltro("fecha_vencimiento_desde", e.target.value)}
              />
            </Field>
            <Field etiqueta="Vencimiento hasta">
              <input
                type="date"
                className={inputClass}
                value={valores.fecha_vencimiento_hasta}
                onChange={(e) => setFiltro("fecha_vencimiento_hasta", e.target.value)}
              />
            </Field>
          </div>
        ) : null}
      </div>

      <FiltrosActivos chips={chipsActivos} onQuitar={(clave) => setFiltro(clave, clave === "estado_laboral" ? "Activo" : "")} onLimpiar={limpiar} />

      {cargando ? (
        <ListaCargando mensaje="Cargando cumplimientos…" />
      ) : (
        <>
          <Table
            columnas={[
              { clave: "persona", etiqueta: "Trabajador" },
              { clave: "contexto", etiqueta: "Cargo / proyecto" },
              { clave: "cap", etiqueta: "Capacitación" },
              { clave: "real", etiqueta: "Realización" },
              { clave: "vence", etiqueta: "Vencimiento" },
              { clave: "estado", etiqueta: "Estado" },
              { clave: "acciones", etiqueta: "", clase: "w-px whitespace-nowrap" },
            ]}
            filas={items.map((item) => [
              <span key="p" className="flex flex-col">
                <Link
                  href={`/personal/${item.persona_id_ext}`}
                  className="font-medium text-hseq-800 underline-offset-2 hover:underline"
                >
                  {item.persona_nombre ?? `Persona ${item.persona_id_ext}`}
                </Link>
                <span className="text-xs text-slate-500">{item.numero_documento ?? "—"}</span>
              </span>,
              contextoPersona(item),
              <span key="c" className="flex flex-col">
                <span>{item.capacitacion_nombre}</span>
                <span className="text-xs text-slate-500">{item.capacitacion_codigo}</span>
              </span>,
              formatoFecha(item.fecha_realizacion),
              formatoFecha(item.fecha_vencimiento),
              <Badge key="e" tono={tonoEstado(item.estado_calculado)}>
                {ETIQUETAS_ESTADO[item.estado_calculado] ?? item.estado_calculado}
              </Badge>,
              <span key="a" className="flex flex-nowrap gap-1">
                <Button
                  type="button"
                  variante="ghost"
                  className="px-2"
                  title="Detalle"
                  aria-label="Ver detalle"
                  onClick={() => void verDetalle(item)}
                >
                  <Eye className="h-4 w-4" aria-hidden />
                </Button>
                <Button
                  type="button"
                  variante="ghost"
                  className="px-2"
                  title="Situación del trabajador"
                  aria-label="Ver situación del trabajador"
                  onClick={() => void verSituacion(item.persona_id_ext)}
                >
                  <UserRound className="h-4 w-4" aria-hidden />
                </Button>
              </span>,
            ])}
            vacio="No hay obligaciones para los filtros seleccionados."
          />
          <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargarListado(p)} />
        </>
      )}

      <Modal
        abierto={detalle !== null}
        titulo="Trazabilidad del cumplimiento"
        onCerrar={() => setDetalle(null)}
      >
        {detalle ? (
          <div className="space-y-4 text-sm">
            <dl className="grid gap-3 sm:grid-cols-2">
              <Dato etiqueta="Trabajador" valor={detalle.trabajador.nombre ?? "—"} />
              <Dato etiqueta="Documento" valor={detalle.trabajador.documento ?? "—"} />
              <Dato etiqueta="Cargo" valor={detalle.trabajador.cargo ?? "—"} />
              <Dato etiqueta="Proyecto" valor={detalle.trabajador.proyecto ?? "—"} />
              <Dato etiqueta="Estado laboral" valor={detalle.trabajador.estado_laboral ?? "—"} />
              <Dato
                etiqueta="Capacitación"
                valor={`${detalle.capacitacion.codigo ?? ""} — ${detalle.capacitacion.nombre ?? ""}`}
              />
              <Dato
                etiqueta="Aplicabilidad"
                valor={`${detalle.aplicabilidad.aplica ? "Sí (Matriz)" : "Sin regla de matriz"} · ${detalle.aplicabilidad.fuente}`}
              />
              <Dato
                etiqueta="Obligación"
                valor={`Asignación ${detalle.obligacion.asignacion_id} · ${detalle.obligacion.origen ?? "—"} · ${detalle.obligacion.fuente}`}
              />
              <Dato
                etiqueta="Fecha programada"
                valor={`${detalle.programacion.fecha_programada ?? "—"} · ${detalle.programacion.fuente}`}
              />
              <Dato
                etiqueta="Fecha ejecutada"
                valor={`${formatoFecha(detalle.ejecucion.fecha_sesion ?? detalle.ejecucion.fecha_realizacion)} · ${detalle.ejecucion.fuente}`}
              />
              <Dato
                etiqueta="Asistencia"
                valor={`${etiquetaAsistencia(detalle.asistencia.estado)} · ${detalle.asistencia.fuente}`}
              />
              <Dato
                etiqueta="Evaluación"
                valor={
                  detalle.evaluacion.requiere
                    ? `Nota ${detalle.evaluacion.nota_obtenida ?? "—"} / mínima ${detalle.evaluacion.nota_minima} · ${detalle.evaluacion.fuente}`
                    : `No requiere · ${detalle.evaluacion.fuente}`
                }
              />
              <Dato etiqueta="Certificado requerido" valor={siNo(detalle.soportes.requiere_certificado)} />
              <Dato etiqueta="Lista de asistencia requerida" valor={siNo(detalle.soportes.requiere_listado)} />
              <Dato
                etiqueta="Vigencia"
                valor={`${detalle.vigencia.nombre ?? "Sin vencimiento"} · vence ${formatoFecha(detalle.vigencia.fecha_vencimiento)} · ${detalle.vigencia.fuente}`}
              />
              <div>
                <dt className="text-xs uppercase text-slate-500">Estado actual</dt>
                <dd className="mt-1">
                  <Badge tono={tonoEstado(detalle.estado_actual)}>
                    {ETIQUETAS_ESTADO[detalle.estado_actual] ?? detalle.estado_actual}
                  </Badge>
                </dd>
              </div>
            </dl>
            <div>
              <p className="mb-1 text-xs uppercase text-slate-500">Evidencias ({detalle.soportes.fuente})</p>
              <ListaEvidencias soportes={detalle.soportes.items} />
            </div>
          </div>
        ) : null}
      </Modal>
    </>
  );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
  return (
    <div>
      <dt className="text-xs uppercase text-slate-500">{etiqueta}</dt>
      <dd className="mt-1 text-slate-800">{valor}</dd>
    </div>
  );
}
