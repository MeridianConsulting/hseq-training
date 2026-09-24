"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { ListaEvidencias } from "@/app/(app)/cumplimientos/evidencias";
import { procesoPermiteFiltroProyecto } from "@/components/dashboard/filtro-periodo";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Field, fieldClassAnio, inputClass, inputClassAnio } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, MasFiltros, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { useDebouncedCallback, useFiltrosUrl } from "@/hooks/useFiltrosUrl";
import { Eye, UserRound } from "lucide-react";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";
import type {
  ConsolidadCumplimientos,
  ConsultaCumplimiento,
  CumplimientoPorCapacitacion,
  DetalleConsultaCumplimiento,
  OpcionesConsultaCumplimientos,
  SituacionTrabajadorCumplimiento,
  TipoPeriodoDashboard,
} from "@/lib/tipos";

const hoy = new Date();

const FILTROS_DEFAULT = {
  buscar: "",
  capacitacion_id: "",
  condicion: "",
  cargo_id: "",
  proceso_id: "",
  proyecto: "",
  tipo_capacitacion_id: "",
  es_tarea_critica: "",
  estado_laboral: "Activo",
  tipo: "mensual" as TipoPeriodoDashboard,
  anio: String(hoy.getFullYear()),
  mes: String(hoy.getMonth() + 1),
  trimestre: String(Math.ceil((hoy.getMonth() + 1) / 3)),
  semestre: String(hoy.getMonth() + 1 <= 6 ? 1 : 2),
};

const ETIQUETAS_ESTADO: Record<string, string> = {
  PENDIENTE: "Pendiente",
  PENDIENTE_PROXIMA_A_VENCER: "Próxima a vencer",
  PENDIENTE_VENCIDA: "Pendiente vencida",
  COMPLETADA: "Cumple",
  PROXIMA_A_VENCER: "Próxima a vencer",
  VENCIDA: "Vencida",
};

const MESES = [
  "Enero",
  "Febrero",
  "Marzo",
  "Abril",
  "Mayo",
  "Junio",
  "Julio",
  "Agosto",
  "Septiembre",
  "Octubre",
  "Noviembre",
  "Diciembre",
];

function aniosDisponibles(): number[] {
  const actual = new Date().getFullYear();
  const lista: number[] = [];
  for (let anio = actual - 2; anio <= actual + 1; anio += 1) {
    lista.push(anio);
  }
  return lista;
}

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

function etiquetaAsistencia(valor: string | null | undefined): string {
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

function etiquetaCondicion(valor: string): string {
  if (valor === "ejecutadas") return "Ejecutadas";
  if (valor === "pendientes") return "Pendientes";
  if (valor === "fuera_de_tiempo") return "Fuera de tiempo";
  if (valor === "vigentes") return "Vigentes";
  if (valor === "vencidas") return "Vencidas";
  return valor;
}

function paramsPeriodo(valores: typeof FILTROS_DEFAULT) {
  return {
    tipo: valores.tipo || "mensual",
    anio: valores.anio || undefined,
    mes: valores.tipo === "mensual" ? valores.mes || undefined : undefined,
    trimestre: valores.tipo === "trimestral" ? valores.trimestre || undefined : undefined,
    semestre: valores.tipo === "semestral" ? valores.semestre || undefined : undefined,
  };
}

export default function Page() {
  return (
    <RequierePermiso permiso="cumplimientos.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { valores, setFiltro, setVarios, limpiar } = useFiltrosUrl(FILTROS_DEFAULT, {
    keysDebounce: ["buscar"],
  });
  const [consolidados, setConsolidados] = useState<CumplimientoPorCapacitacion[]>([]);
  const [totales, setTotales] = useState({
    programadas: 0,
    ejecutadas: 0,
    pendientes: 0,
    ejecutadas_fuera_de_tiempo: 0,
  });
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
    Boolean(valores.cargo_id || valores.tipo_capacitacion_id || valores.es_tarea_critica),
  );
  const [capDetalle, setCapDetalle] = useState<CumplimientoPorCapacitacion | null>(null);
  const [personas, setPersonas] = useState<ConsultaCumplimiento[]>([]);
  const [paginaPers, setPaginaPers] = useState(1);
  const [ultimaPers, setUltimaPers] = useState(1);
  const [cargandoPers, setCargandoPers] = useState(false);
  const [detalle, setDetalle] = useState<DetalleConsultaCumplimiento | null>(null);
  const [situacion, setSituacion] = useState<SituacionTrabajadorCumplimiento | null>(null);

  const muestraProyecto = procesoPermiteFiltroProyecto(valores.proceso_id, opciones.procesos);

  async function cargarConsolidados() {
    setCargando(true);
    try {
      const respuesta = await apiGet<ConsolidadCumplimientos>(
        withQuery("/api/cumplimientos/consulta/por-capacitacion", {
          ...paramsPeriodo(valores),
          buscar: valores.buscar.trim() || undefined,
          capacitacion_id: valores.capacitacion_id || undefined,
          condicion: valores.condicion || undefined,
          cargo_id: valores.cargo_id || undefined,
          proceso_id: valores.proceso_id || undefined,
          proyecto: muestraProyecto ? valores.proyecto || undefined : undefined,
          tipo_capacitacion_id: valores.tipo_capacitacion_id || undefined,
          es_tarea_critica: valores.es_tarea_critica || undefined,
          estado_laboral: valores.estado_laboral || "todos",
        }),
      );
      if (respuesta.cancelada) {
        return;
      }
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar los cumplimientos.");
        setConsolidados([]);
        return;
      }
      setConsolidados(respuesta.data.items ?? []);
      setTotales(
        respuesta.data.totales ?? {
          programadas: 0,
          ejecutadas: 0,
          pendientes: 0,
          ejecutadas_fuera_de_tiempo: 0,
        },
      );
      setError(null);
    } finally {
      setCargando(false);
    }
  }

  async function cargarPersonas(cap: CumplimientoPorCapacitacion, paginaActual = 1) {
    setCargandoPers(true);
    try {
      const respuesta = await apiGet<ListaPaginada<ConsultaCumplimiento>>(
        withQuery("/api/cumplimientos/consulta", {
          page: paginaActual,
          per_page: 20,
          ...paramsPeriodo(valores),
          capacitacion_id: cap.capacitacion_id,
          buscar: valores.buscar.trim() || undefined,
          condicion: valores.condicion || undefined,
          cargo_id: valores.cargo_id || undefined,
          proceso_id: valores.proceso_id || undefined,
          proyecto: muestraProyecto ? valores.proyecto || undefined : undefined,
          tipo_capacitacion_id: valores.tipo_capacitacion_id || undefined,
          es_tarea_critica: valores.es_tarea_critica || undefined,
          estado_laboral: valores.estado_laboral || "todos",
        }),
      );
      if (respuesta.cancelada) {
        return;
      }
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar las personas de la capacitación.");
        return;
      }
      setPersonas(respuesta.data.items);
      setPaginaPers(respuesta.data.pagination.current_page);
      setUltimaPers(respuesta.data.pagination.last_page);
      setError(null);
    } finally {
      setCargandoPers(false);
    }
  }

  useDebouncedCallback(
    () => {
      void cargarConsolidados();
    },
    [
      valores.buscar,
      valores.capacitacion_id,
      valores.condicion,
      valores.cargo_id,
      valores.proceso_id,
      valores.proyecto,
      valores.tipo_capacitacion_id,
      valores.es_tarea_critica,
      valores.estado_laboral,
      valores.tipo,
      valores.anio,
      valores.mes,
      valores.trimestre,
      valores.semestre,
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
    valores.tipo_capacitacion_id,
    valores.es_tarea_critica,
    valores.estado_laboral !== "Activo" ? valores.estado_laboral : "",
  ].filter(Boolean).length;

  const chipsActivos = useMemo(() => {
    const chips: ChipFiltro[] = [];
    if (valores.buscar.trim()) {
      chips.push({ clave: "buscar", etiqueta: "Buscar", valor: valores.buscar.trim() });
    }
    if (valores.capacitacion_id) {
      const cap = capacitaciones.find((c) => String(c.capacitacion_id) === valores.capacitacion_id);
      chips.push({
        clave: "capacitacion_id",
        etiqueta: "Capacitación",
        valor: cap ? `${cap.codigo} — ${cap.nombre}` : valores.capacitacion_id,
      });
    }
    if (valores.condicion) {
      chips.push({
        clave: "condicion",
        etiqueta: "Condición",
        valor: etiquetaCondicion(valores.condicion),
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
    if (muestraProyecto && valores.proyecto) {
      chips.push({ clave: "proyecto", etiqueta: "Proyecto", valor: valores.proyecto });
    }
    if (valores.tipo_capacitacion_id) {
      const tipo = opciones.tipos.find((t) => String(t.tipo_capacitacion_id) === valores.tipo_capacitacion_id);
      chips.push({
        clave: "tipo_capacitacion_id",
        etiqueta: "Tipo",
        valor: tipo?.nombre ?? valores.tipo_capacitacion_id,
      });
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
  }, [valores, capacitaciones, opciones, muestraProyecto]);

  async function abrirCapacitacion(item: CumplimientoPorCapacitacion) {
    setCapDetalle(item);
    setPersonas([]);
    await cargarPersonas(item, 1);
  }

  async function verDetalle(item: ConsultaCumplimiento) {
    const respuesta = await apiGet<DetalleConsultaCumplimiento>(
      `/api/cumplimientos/consulta/${item.asignacion_id}`,
    );
    if (respuesta.cancelada) {
      return;
    }
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
    if (respuesta.cancelada) {
      return;
    }
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
        descripcion="Consolide el resultado de las capacitaciones programadas (Asignaciones) y ejecutadas (Tablero de Cronograma). Esta pantalla es solo consulta: no registre asistencia, notas ni soportes aquí."
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
                href={`/personal/detalle?id=${situacion.trabajador.persona_id_ext}`}
                prefetch={false}
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
        <Field etiqueta="Proceso">
          <select
            className={inputClass}
            value={valores.proceso_id}
            onChange={(e) => {
              const procesoId = e.target.value;
              const mantiene = procesoPermiteFiltroProyecto(procesoId, opciones.procesos);
              setVarios({
                proceso_id: procesoId,
                proyecto: mantiene ? valores.proyecto : "",
              });
            }}
          >
            <option value="">Todos</option>
            {opciones.procesos.map((p) => (
              <option key={p.proceso_id} value={p.proceso_id}>
                {p.nombre}
              </option>
            ))}
          </select>
        </Field>
        {muestraProyecto ? (
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
        ) : null}
        <Field etiqueta="Período">
          <select
            className={inputClass}
            value={valores.tipo}
            onChange={(e) => setFiltro("tipo", e.target.value)}
          >
            <option value="mensual">Mensual</option>
            <option value="trimestral">Trimestral</option>
            <option value="semestral">Semestral</option>
            <option value="anual">Anual</option>
          </select>
        </Field>
        <Field etiqueta="Año" className={fieldClassAnio}>
          <select
            className={inputClassAnio}
            value={valores.anio}
            onChange={(e) => setFiltro("anio", e.target.value)}
          >
            {aniosDisponibles().map((anio) => (
              <option key={anio} value={anio}>
                {anio}
              </option>
            ))}
          </select>
        </Field>
        {valores.tipo === "mensual" ? (
          <Field etiqueta="Mes">
            <select
              className={inputClass}
              value={valores.mes}
              onChange={(e) => setFiltro("mes", e.target.value)}
            >
              {MESES.map((nombre, idx) => (
                <option key={nombre} value={idx + 1}>
                  {nombre}
                </option>
              ))}
            </select>
          </Field>
        ) : null}
        {valores.tipo === "trimestral" ? (
          <Field etiqueta="Trimestre">
            <select
              className={inputClass}
              value={valores.trimestre}
              onChange={(e) => setFiltro("trimestre", e.target.value)}
            >
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
            </select>
          </Field>
        ) : null}
        {valores.tipo === "semestral" ? (
          <Field etiqueta="Semestre">
            <select
              className={inputClass}
              value={valores.semestre}
              onChange={(e) => setFiltro("semestre", e.target.value)}
            >
              <option value="1">1</option>
              <option value="2">2</option>
            </select>
          </Field>
        ) : null}
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={valores.buscar}
            onChange={(e) => setFiltro("buscar", e.target.value)}
            placeholder="Código, capacitación, nombre o documento"
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
        <Field etiqueta="Condición">
          <select
            className={inputClass}
            value={valores.condicion}
            onChange={(e) => setFiltro("condicion", e.target.value)}
          >
            <option value="">Todas</option>
            <option value="ejecutadas">Ejecutadas</option>
            <option value="pendientes">Pendientes</option>
            <option value="fuera_de_tiempo">Fuera de tiempo</option>
            <option value="vigentes">Vigentes</option>
            <option value="vencidas">Vencidas</option>
          </select>
        </Field>
      </Filters>

      <MasFiltros
        abierto={masFiltros}
        onToggle={() => setMasFiltros((abierto) => !abierto)}
        extrasActivos={extrasActivos}
      >
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
        <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={valores.es_tarea_critica === "1"}
            onChange={(e) => setFiltro("es_tarea_critica", e.target.checked ? "1" : "")}
          />
          Solo tareas críticas
        </label>
      </MasFiltros>

      <FiltrosActivos
        chips={chipsActivos}
        onQuitar={(clave) => setFiltro(clave, clave === "estado_laboral" ? "Activo" : "")}
        onLimpiar={limpiar}
      />

      {!cargando && consolidados.length > 0 ? (
        <p className="mb-3 text-sm text-slate-600">
          Periodo: {totales.programadas} programadas · {totales.ejecutadas} ejecutadas ·{" "}
          {totales.pendientes} pendientes · {totales.ejecutadas_fuera_de_tiempo} fuera de tiempo
          {" "}(sin penalización porcentual; la fórmula definitiva está pendiente).
        </p>
      ) : null}

      {cargando ? (
        <ListaCargando mensaje="Cargando cumplimientos por capacitación…" />
      ) : (
        <Table
          columnas={[
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "prog", etiqueta: "Programadas" },
            { clave: "ejec", etiqueta: "Ejecutadas" },
            { clave: "pend", etiqueta: "Pendientes" },
            { clave: "fuera", etiqueta: "Fuera de tiempo" },
            { clave: "vig", etiqueta: "Vigencia" },
            { clave: "acc", etiqueta: "", clase: "w-px whitespace-nowrap" },
          ]}
          filas={consolidados.map((item) => [
            <span key="c" className="flex flex-col">
              <span className="font-medium text-hseq-900">{item.nombre}</span>
              <span className="text-xs text-slate-500">
                {item.codigo}
                {item.tipo_nombre ? ` · ${item.tipo_nombre}` : ""}
                {item.es_tarea_critica ? " · Crítica" : ""}
              </span>
            </span>,
            item.programadas,
            item.ejecutadas,
            item.pendientes,
            item.ejecutadas_fuera_de_tiempo > 0 ? (
              <span key="f" className="font-medium text-amber-700">
                {item.ejecutadas_fuera_de_tiempo}
              </span>
            ) : (
              0
            ),
            item.vigencia_nombre ?? "Sin renovación",
            <Button
              key="a"
              type="button"
              variante="ghost"
              className="px-2"
              title="Ver personas"
              aria-label="Ver personas de la capacitación"
              onClick={() => void abrirCapacitacion(item)}
            >
              <Eye className="h-4 w-4" aria-hidden />
            </Button>,
          ])}
          vacio="No hay capacitaciones con asignaciones para los filtros del período. Ajuste el período o cree asignaciones."
        />
      )}

      <Modal
        abierto={capDetalle !== null}
        titulo={
          capDetalle
            ? `${capDetalle.codigo} — ${capDetalle.nombre}`
            : "Personas de la capacitación"
        }
        onCerrar={() => {
          setCapDetalle(null);
          setPersonas([]);
        }}
      >
        {capDetalle ? (
          <div className="space-y-4">
            <p className="text-sm text-slate-600">
              {capDetalle.programadas} programadas · {capDetalle.ejecutadas} ejecutadas ·{" "}
              {capDetalle.pendientes} pendientes · {capDetalle.ejecutadas_fuera_de_tiempo} fuera de
              tiempo. Periodo {formatoFecha(capDetalle.fecha_desde)} →{" "}
              {formatoFecha(capDetalle.fecha_hasta)}.
            </p>
            {cargandoPers ? (
              <p className="text-sm text-slate-500">Cargando personas…</p>
            ) : (
              <>
                <Table
                  columnas={[
                    { clave: "doc", etiqueta: "Documento" },
                    { clave: "nom", etiqueta: "Trabajador" },
                    { clave: "car", etiqueta: "Cargo" },
                    { clave: "proy", etiqueta: "Proyecto" },
                    { clave: "desde", etiqueta: "Desde" },
                    { clave: "hasta", etiqueta: "Hasta" },
                    { clave: "real", etiqueta: "Fecha real" },
                    { clave: "asis", etiqueta: "Asistencia" },
                    { clave: "nota", etiqueta: "Nota" },
                    { clave: "sop", etiqueta: "Soporte" },
                    { clave: "opc", etiqueta: "Plazo" },
                    { clave: "est", etiqueta: "Estado" },
                    { clave: "acc", etiqueta: "", clase: "w-px whitespace-nowrap" },
                  ]}
                  filas={personas.map((p) => [
                    p.numero_documento ?? "—",
                    p.persona_nombre ?? `Persona ${p.persona_id_ext}`,
                    p.cargo ?? "—",
                    p.proyecto ?? "—",
                    formatoFecha(p.fecha_asignacion),
                    formatoFecha(p.fecha_limite_cumplimiento),
                    formatoFecha(p.fecha_realizacion),
                    etiquetaAsistencia(p.estado_asistencia),
                    p.requiere_evaluacion
                      ? p.nota_evaluacion !== null
                        ? String(p.nota_evaluacion)
                        : "—"
                      : "N/A",
                    p.soportes_count > 0 ? `${p.soportes_count}` : p.requiere_certificado ? "Falta" : "N/A",
                    p.fecha_realizacion
                      ? p.ejecutada_fuera_de_tiempo
                        ? "Fuera de tiempo"
                        : "Dentro del tiempo"
                      : p.estado_calculado === "PENDIENTE_VENCIDA"
                        ? "Pendiente fuera de plazo"
                        : "—",
                    <Badge key="e" tono={tonoEstado(p.estado_calculado)}>
                      {ETIQUETAS_ESTADO[p.estado_calculado] ?? p.estado_calculado}
                    </Badge>,
                    <span key="a" className="flex flex-nowrap gap-1">
                      <Button
                        type="button"
                        variante="ghost"
                        className="px-2"
                        title="Trazabilidad"
                        onClick={() => void verDetalle(p)}
                      >
                        <Eye className="h-4 w-4" aria-hidden />
                      </Button>
                      <Button
                        type="button"
                        variante="ghost"
                        className="px-2"
                        title="Situación del trabajador"
                        onClick={() => void verSituacion(p.persona_id_ext)}
                      >
                        <UserRound className="h-4 w-4" aria-hidden />
                      </Button>
                    </span>,
                  ])}
                  vacio="No hay personas asignadas a esta capacitación en el período."
                />
                <Pagination
                  pagina={paginaPers}
                  ultima={ultimaPers}
                  onCambiar={(p) => {
                    if (capDetalle) void cargarPersonas(capDetalle, p);
                  }}
                />
              </>
            )}
          </div>
        ) : null}
      </Modal>

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
                etiqueta="Plazo Desde → Hasta"
                valor={`${formatoFecha(detalle.programacion.fecha_desde)} → ${formatoFecha(detalle.programacion.fecha_hasta ?? detalle.programacion.fecha_programada)} · ${detalle.programacion.fuente}`}
              />
              <Dato
                etiqueta="Fecha ejecutada"
                valor={`${formatoFecha(detalle.ejecucion.fecha_sesion ?? detalle.ejecucion.fecha_realizacion)} · ${detalle.ejecucion.fuente}`}
              />
              <Dato
                etiqueta="¿Se ejecutó?"
                valor={detalle.ejecucion.fecha_realizacion || detalle.ejecucion.fecha_sesion ? "Sí" : "No"}
              />
              <Dato
                etiqueta="Plazo"
                valor={
                  detalle.ejecutada_fuera_de_tiempo || detalle.ejecucion.fuera_de_tiempo
                    ? "Fuera del tiempo (realización posterior a fecha hasta)"
                    : detalle.ejecucion.fecha_realizacion || detalle.ejecucion.fecha_sesion
                      ? "Dentro del tiempo"
                      : "Sin ejecución"
                }
              />
              <Dato
                etiqueta="Asistencia"
                valor={`${etiquetaAsistencia(detalle.asistencia.estado)} · ${detalle.asistencia.fuente}`}
              />
              <Dato
                etiqueta="Evaluación"
                valor={
                  detalle.evaluacion.requiere
                    ? `Nota ${detalle.evaluacion.nota_obtenida ?? "—"} / mínima ${detalle.evaluacion.nota_minima} · ${
                        detalle.evaluacion.aprobada === true
                          ? "Aprobada"
                          : detalle.evaluacion.aprobada === false
                            ? "No aprobada"
                            : "Pendiente"
                      } · ${detalle.evaluacion.fuente}`
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
                <dt className="text-xs uppercase text-slate-500">Estado calculado</dt>
                <dd className="mt-1">
                  <Badge tono={tonoEstado(detalle.estado_actual)}>
                    {ETIQUETAS_ESTADO[detalle.estado_actual] ?? detalle.estado_actual}
                  </Badge>
                </dd>
              </div>
            </dl>
            <div>
              <p className="mb-1 text-xs uppercase text-slate-500">
                Evidencias (solo consulta · {detalle.soportes.fuente})
              </p>
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
