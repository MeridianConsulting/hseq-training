"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { BotonExportarFlotante } from "@/components/ui/boton-exportar-flotante";
import { Button } from "@/components/ui/button";
import { Field, fieldClassAnio, inputClass, inputClassAnio } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { MasFiltros } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { Download } from "lucide-react";
import { FichaTrabajador, GruposCapacitacion, ListaPeriodos } from "./historial";
import { apiDownload, apiGet, withQuery } from "@/lib/api";
import { humanizarNombreUnidad, procesoRequiereProyecto } from "@/lib/catalogos";
import type {
  FichaTrabajadorReporte,
  GrupoHistorial,
  OpcionesAlertas,
  PeriodoHistorial,
  PersonaCorporativa,
  ResultadoReporte,
  SoporteCumplimiento,
  TipoPeriodoDashboard,
  TotalesReporte,
} from "@/lib/tipos";
import { TIPOS_REPORTE } from "@/lib/tipos";

const VACIO = "No se encontraron registros para los filtros seleccionados.";

const ESTADOS = [
  "PENDIENTE",
  "PENDIENTE_PROXIMA_A_VENCER",
  "PENDIENTE_VENCIDA",
  "COMPLETADA",
  "PROXIMA_A_VENCER",
  "VENCIDA",
];

const TIPOS_DETALLE = [
  "cumplimiento_general",
  "vencidas",
  "pendientes",
  "tareas_criticas",
];

const TIPOS_AGREGADOS = [
  "cumplimiento_trabajador",
  "cumplimiento_proceso",
  "cumplimiento_proyecto",
];

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
  return [actual - 2, actual - 1, actual, actual + 1];
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

/** Misma ventana que DashboardService::periodo. */
function rangoDePeriodo(
  tipoPeriodo: TipoPeriodoDashboard,
  anio: number,
  mes: number,
  trimestre: number,
  semestre: number,
): { desde: string; hasta: string } {
  if (tipoPeriodo === "mensual") {
    const ultimo = new Date(anio, mes, 0).getDate();
    return { desde: `${anio}-${pad(mes)}-01`, hasta: `${anio}-${pad(mes)}-${pad(ultimo)}` };
  }
  if (tipoPeriodo === "trimestral") {
    const inicioMes = (trimestre - 1) * 3 + 1;
    const finMes = inicioMes + 2;
    const ultimo = new Date(anio, finMes, 0).getDate();
    return { desde: `${anio}-${pad(inicioMes)}-01`, hasta: `${anio}-${pad(finMes)}-${pad(ultimo)}` };
  }
  if (tipoPeriodo === "semestral") {
    const inicioMes = semestre === 1 ? 1 : 7;
    const finMes = semestre === 1 ? 6 : 12;
    const ultimo = new Date(anio, finMes, 0).getDate();
    return { desde: `${anio}-${pad(inicioMes)}-01`, hasta: `${anio}-${pad(finMes)}-${pad(ultimo)}` };
  }
  return { desde: `${anio}-01-01`, hasta: `${anio}-12-31` };
}

function formatoFecha(valor: unknown): string {
  if (typeof valor !== "string" || !valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function texto(valor: unknown): string {
  if (valor === null || valor === undefined || valor === "") return "—";
  if (typeof valor === "boolean") return valor ? "Sí" : "No";
  if (typeof valor === "number") return String(valor);
  return String(valor);
}

function etiquetaEstado(estado: unknown): string {
  const mapa: Record<string, string> = {
    PENDIENTE: "Pendiente",
    PENDIENTE_PROXIMA_A_VENCER: "Pendiente próxima a vencer",
    PENDIENTE_VENCIDA: "Pendiente vencida",
    COMPLETADA: "Completada",
    PROXIMA_A_VENCER: "Próxima a vencer",
    VENCIDA: "Vencida",
    CONVOCADO: "Convocado",
    ASISTIO: "Asistió",
    TARDE: "Tarde",
    AUSENTE: "Ausente",
    AUTOMATICA: "Automática (matriz)",
    MANUAL: "Manual",
    INDUCCION: "Inducción",
    REINDUCCION: "Reinducción",
  };
  const clave = typeof estado === "string" ? estado : "";
  return mapa[clave] ?? (clave || "—");
}

function columnasDe(tipo: string): { clave: string; etiqueta: string }[] {
  if (tipo === "cumplimiento_trabajador") {
    return [
      { clave: "documento", etiqueta: "Cédula" },
      { clave: "trabajador", etiqueta: "Trabajador" },
      { clave: "cargo", etiqueta: "Cargo" },
      { clave: "proceso", etiqueta: "Proceso" },
      { clave: "proyecto", etiqueta: "Proyecto" },
      { clave: "programadas", etiqueta: "Programadas" },
      { clave: "ejecutadas", etiqueta: "Ejecutadas" },
      { clave: "pendientes", etiqueta: "Pendientes" },
      { clave: "vencidas", etiqueta: "Fuera de plazo" },
      { clave: "ejecutadas_fuera_de_tiempo", etiqueta: "Fuera de tiempo" },
      { clave: "porcentaje", etiqueta: "% cumplimiento" },
    ];
  }
  if (tipo === "cumplimiento_proceso" || tipo === "cumplimiento_proyecto") {
    const grupo = tipo === "cumplimiento_proceso" ? "Proceso" : "Proyecto";
    return [
      { clave: "grupo", etiqueta: grupo },
      { clave: "programadas", etiqueta: "Programadas" },
      { clave: "ejecutadas", etiqueta: "Ejecutadas" },
      { clave: "pendientes", etiqueta: "Pendientes" },
      { clave: "vencidas", etiqueta: "Fuera de plazo" },
      { clave: "ejecutadas_fuera_de_tiempo", etiqueta: "Fuera de tiempo" },
      { clave: "porcentaje", etiqueta: "% cumplimiento" },
    ];
  }
  if (tipo === "horas") {
    return [
      { clave: "documento", etiqueta: "Documento" },
      { clave: "trabajador", etiqueta: "Trabajador" },
      { clave: "capacitacion", etiqueta: "Capacitación" },
      { clave: "proceso", etiqueta: "Proceso" },
      { clave: "proyecto", etiqueta: "Proyecto" },
      { clave: "fecha_realizacion", etiqueta: "Fecha de realización" },
      { clave: "horas_efectivas", etiqueta: "Horas" },
    ];
  }
  if (tipo === "evidencias_faltantes") {
    return [
      { clave: "trabajador", etiqueta: "Trabajador" },
      { clave: "documento", etiqueta: "Documento" },
      { clave: "capacitacion", etiqueta: "Capacitación" },
      { clave: "fecha_realizacion", etiqueta: "Fecha de realización" },
      { clave: "estado", etiqueta: "Estado" },
      { clave: "requiere_certificado", etiqueta: "Requiere certificado" },
    ];
  }
  if (tipo === "proximas") {
    return [
      { clave: "trabajador", etiqueta: "Trabajador" },
      { clave: "documento", etiqueta: "Documento" },
      { clave: "cargo", etiqueta: "Cargo" },
      { clave: "proceso", etiqueta: "Proceso" },
      { clave: "proyecto", etiqueta: "Proyecto" },
      { clave: "capacitacion_nombre", etiqueta: "Capacitación" },
      { clave: "fecha_vencimiento", etiqueta: "Vencimiento" },
      { clave: "dias_restantes", etiqueta: "Días" },
    ];
  }
  const cols = [
    { clave: "documento", etiqueta: "Documento" },
    { clave: "trabajador", etiqueta: "Trabajador" },
    { clave: "cargo", etiqueta: "Cargo" },
    { clave: "proceso", etiqueta: "Proceso" },
    { clave: "proyecto", etiqueta: "Proyecto" },
    { clave: "capacitacion", etiqueta: "Capacitación" },
    { clave: "origen", etiqueta: "Origen" },
    { clave: "estado", etiqueta: "Estado" },
    { clave: "fecha_desde", etiqueta: "Fecha desde" },
    { clave: "fecha_hasta", etiqueta: "Fecha hasta" },
    { clave: "fecha_realizacion", etiqueta: "Fecha real" },
    { clave: "oportunidad", etiqueta: "Oportunidad" },
    { clave: "fecha_vencimiento", etiqueta: "Vencimiento" },
  ];
  if (tipo === "tareas_criticas" || tipo === "cumplimiento_general") {
    cols.push({ clave: "es_tarea_critica", etiqueta: "Crítica" });
  }
  return cols;
}

function celda(tipo: string, clave: string, item: Record<string, unknown>) {
  const valor = item[clave];
  if (clave.includes("fecha") || clave === "fecha") return formatoFecha(valor);
  if (clave === "estado" || clave === "estado_asistencia" || clave === "origen") return etiquetaEstado(valor);
  if (clave === "oportunidad") return texto(valor);
  if (clave === "porcentaje") return valor === null || valor === undefined ? "—" : `${valor}%`;
  if (clave === "es_tarea_critica" || clave === "tiene_soporte" || clave === "requiere_certificado") {
    return valor ? "Sí" : "No";
  }
  if (clave === "trabajador" && item.persona_id_ext) {
    return (
      <Link
        prefetch={false}
        href={withQuery("/asignaciones", {
          persona_id: Number(item.persona_id_ext),
          nombre: typeof item.trabajador === "string" ? item.trabajador : undefined,
          documento: typeof item.documento === "string" ? item.documento : undefined,
        })}
        className="font-medium text-hseq-800 underline-offset-2 hover:underline"
      >
        {texto(item.trabajador)}
      </Link>
    );
  }
  if (clave === "periodicidad") return humanizarNombreUnidad(texto(valor)) || "—";
  return texto(valor);
}

function valorProgramadas(totales: TotalesReporte): number {
  return totales.programadas ?? totales.asignadas;
}

function valorEjecutadas(totales: TotalesReporte): number {
  return totales.ejecutadas ?? totales.completadas;
}

export default function Page() {
  return (
    <RequierePermiso permiso="reportes.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const hoy = new Date();
  const [tipo, setTipo] = useState(() => {
    if (typeof window === "undefined") return "cumplimiento_general";
    const t = new URLSearchParams(window.location.search).get("tipo");
    if (t && TIPOS_REPORTE.some((op) => op.id === t)) return t;
    return "cumplimiento_general";
  });
  const [tipoPeriodo, setTipoPeriodo] = useState<TipoPeriodoDashboard>("mensual");
  const [anio, setAnio] = useState(hoy.getFullYear());
  const [mes, setMes] = useState(hoy.getMonth() + 1);
  const [trimestre, setTrimestre] = useState(Math.ceil((hoy.getMonth() + 1) / 3));
  const [semestre, setSemestre] = useState(hoy.getMonth() + 1 <= 6 ? 1 : 2);
  const rangoInicial = rangoDePeriodo("mensual", hoy.getFullYear(), hoy.getMonth() + 1, 1, 1);
  const [desde, setDesde] = useState(rangoInicial.desde);
  const [hasta, setHasta] = useState(rangoInicial.hasta);
  const [procesoId, setProcesoId] = useState("");
  const [proyecto, setProyecto] = useState("");
  const [buscar, setBuscar] = useState("");
  const [estado, setEstado] = useState("");
  const [cargoId, setCargoId] = useState("");
  const [capacitacionId, setCapacitacionId] = useState("");
  const [tipoCapId, setTipoCapId] = useState("");
  const [personaId, setPersonaId] = useState("");
  const [consultaTrabajador, setConsultaTrabajador] = useState("");
  const [sugerencias, setSugerencias] = useState<PersonaCorporativa[]>([]);
  const [opciones, setOpciones] = useState<OpcionesAlertas>({
    procesos: [],
    proyectos: [],
    cargos: [],
    capacitaciones: [],
    tipos_capacitacion: [],
  });
  const [items, setItems] = useState<Record<string, unknown>[]>([]);
  const [grupos, setGrupos] = useState<GrupoHistorial[]>([]);
  const [trabajador, setTrabajador] = useState<FichaTrabajadorReporte | null>(null);
  const [historialCargo, setHistorialCargo] = useState<PeriodoHistorial[]>([]);
  const [historialProyecto, setHistorialProyecto] = useState<PeriodoHistorial[]>([]);
  const [historialProceso, setHistorialProceso] = useState<PeriodoHistorial[]>([]);
  const [totales, setTotales] = useState<TotalesReporte | null>(null);
  const [etiquetas, setEtiquetas] = useState<Record<string, string>>({});
  const [titulo, setTitulo] = useState("Reportes");
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [exportando, setExportando] = useState(false);
  const [detalle, setDetalle] = useState<Record<string, unknown> | null>(null);
  const [soportesDetalle, setSoportesDetalle] = useState<SoporteCumplimiento[]>([]);
  const [cargandoDetalle, setCargandoDetalle] = useState(false);
  const [masFiltros, setMasFiltros] = useState(false);
  const [drillItems, setDrillItems] = useState<Record<string, unknown>[]>([]);
  const [drillTitulo, setDrillTitulo] = useState("");
  const [drillAbierto, setDrillAbierto] = useState(false);
  const [cargandoDrill, setCargandoDrill] = useState(false);

  const esHistorial = tipo === "historial_trabajador";
  const muestraProyecto = procesoRequiereProyecto(procesoId, opciones.procesos);
  const permiteDetalle = TIPOS_DETALLE.includes(tipo);
  const permiteDrill = TIPOS_AGREGADOS.includes(tipo);

  function aplicarPeriodo(
    siguienteTipo: TipoPeriodoDashboard,
    siguienteAnio: number,
    siguienteMes: number,
    siguienteTrimestre: number,
    siguienteSemestre: number,
  ) {
    const rango = rangoDePeriodo(
      siguienteTipo,
      siguienteAnio,
      siguienteMes,
      siguienteTrimestre,
      siguienteSemestre,
    );
    setDesde(rango.desde);
    setHasta(rango.hasta);
  }

  const params = useMemo(
    () => ({
      desde: desde || undefined,
      hasta: hasta || undefined,
      proceso_id: procesoId || undefined,
      proyecto: muestraProyecto && proyecto ? proyecto : undefined,
      buscar: esHistorial ? undefined : buscar.trim() || undefined,
      estado: estado || undefined,
      cargo_id_ext: esHistorial ? cargoId || undefined : undefined,
      capacitacion_id: esHistorial ? capacitacionId || undefined : undefined,
      tipo_capacitacion_id: esHistorial ? tipoCapId || undefined : undefined,
      persona_id: esHistorial ? personaId || undefined : undefined,
    }),
    [
      desde,
      hasta,
      procesoId,
      proyecto,
      buscar,
      estado,
      cargoId,
      capacitacionId,
      tipoCapId,
      personaId,
      esHistorial,
      muestraProyecto,
    ],
  );

  const columnas = columnasDe(tipo);

  async function cargar(paginaActual = 1) {
    if (esHistorial && !personaId) {
      setItems([]);
      setGrupos([]);
      setTrabajador(null);
      setHistorialCargo([]);
      setHistorialProyecto([]);
      setHistorialProceso([]);
      setTotales(null);
      setEtiquetas({});
      setTotal(0);
      setError(null);
      setAviso("Seleccione un trabajador para consultar su historial.");
      return;
    }
    const respuesta = await apiGet<ResultadoReporte>(
      withQuery(`/api/reportes/${tipo}`, {
        ...params,
        page: paginaActual,
        per_page: esHistorial ? 20000 : 20,
      }),
    );
    if (respuesta.cancelada) {
      return;
    }
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar el reporte.");
      setItems([]);
      setGrupos([]);
      setTotales(null);
      return;
    }
    const data = respuesta.data;
    setItems(data.items);
    setGrupos(data.grupos ?? []);
    setTrabajador(data.trabajador ?? null);
    setHistorialCargo(data.historial_cargo ?? []);
    setHistorialProyecto(data.historial_proyecto ?? []);
    setHistorialProceso(data.historial_proceso ?? []);
    setTotales(data.totales);
    setEtiquetas(data.filtros_etiqueta ?? {});
    setTitulo(data.titulo);
    setPagina(data.pagination.current_page);
    setUltima(data.pagination.last_page);
    setTotal(data.pagination.total);
    setError(null);
    setAviso(data.pagination.total === 0 ? VACIO : null);
  }

  async function exportar() {
    if (total === 0) {
      setAviso(VACIO);
      return;
    }
    setExportando(true);
    try {
      const hoy = new Date().toISOString().slice(0, 10);
      await apiDownload(withQuery(`/api/reportes/${tipo}/excel`, params), `${tipo}_${hoy}.xlsx`);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "No fue posible exportar el reporte.");
    } finally {
      setExportando(false);
    }
  }

  async function abrirDetalle(item: Record<string, unknown>) {
    setDetalle(item);
    setSoportesDetalle([]);
    const cumplimientoId = typeof item.cumplimiento_id === "number" ? item.cumplimiento_id : null;
    if (!cumplimientoId) return;
    setCargandoDetalle(true);
    const r = await apiGet<SoporteCumplimiento[]>(`/api/cumplimientos/${cumplimientoId}/soportes`);
    setCargandoDetalle(false);
    if (r.success && r.data) {
      setSoportesDetalle(r.data);
    }
  }

  async function abrirDrill(item: Record<string, unknown>) {
    const extras: Record<string, string | number | undefined> = {
      desde: params.desde,
      hasta: params.hasta,
      proceso_id: params.proceso_id,
      proyecto: params.proyecto,
      estado: params.estado,
      page: 1,
      per_page: 100,
    };
    let tituloDrill = "Detalle de registros";
    if (tipo === "cumplimiento_trabajador" && item.persona_id_ext) {
      extras.persona_id = Number(item.persona_id_ext);
      tituloDrill = `Detalle — ${texto(item.trabajador)}`;
    } else if (tipo === "cumplimiento_proceso" && item.grupo_id != null && item.grupo_id !== "") {
      extras.proceso_id = Number(item.grupo_id);
      tituloDrill = `Detalle — ${texto(item.grupo)}`;
    } else if (tipo === "cumplimiento_proyecto") {
      const proy = typeof item.grupo_id === "string" && item.grupo_id
        ? item.grupo_id
        : typeof item.grupo === "string" && item.grupo !== "(Sin proyecto)"
          ? item.grupo
          : undefined;
      extras.proyecto = proy;
      tituloDrill = `Detalle — ${texto(item.grupo)}`;
    }
    setDrillTitulo(tituloDrill);
    setDrillAbierto(true);
    setCargandoDrill(true);
    setDrillItems([]);
    const r = await apiGet<ResultadoReporte>(withQuery("/api/reportes/cumplimiento_general", extras));
    setCargandoDrill(false);
    if (r.cancelada) return;
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible cargar el detalle del grupo.");
      return;
    }
    setDrillItems(r.data.items);
  }

  useEffect(() => {
    void (async () => {
      const respuesta = await apiGet<OpcionesAlertas>("/api/reportes/opciones");
      if (respuesta.success && respuesta.data) {
        setOpciones(respuesta.data);
      }
    })();
  }, []);

  useEffect(() => {
    if (!muestraProyecto && proyecto !== "") {
      setProyecto("");
    }
  }, [muestraProyecto, proyecto]);

  useEffect(() => {
    if (!esHistorial) {
      setSugerencias([]);
      return;
    }
    const q = consultaTrabajador.trim();
    if (q.length < 2) {
      setSugerencias([]);
      return;
    }
    const id = window.setTimeout(() => {
      void (async () => {
        const respuesta = await apiGet<{ items: PersonaCorporativa[] }>(
          withQuery("/api/reportes/trabajadores", { buscar: q }),
        );
        if (respuesta.success && respuesta.data) {
          setSugerencias(respuesta.data.items);
        }
      })();
    }, 250);
    return () => window.clearTimeout(id);
  }, [consultaTrabajador, esHistorial]);

  useEffect(() => {
    const id = window.setTimeout(() => {
      void cargar(1);
    }, 250);
    return () => window.clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tipo, params]);

  const muestraEstado = [
    "cumplimiento_general",
    "cumplimiento_trabajador",
    "tareas_criticas",
    "historial_trabajador",
  ].includes(tipo);
  const muestraPeriodo = tipo !== "proximas";

  return (
    <>
      <PageHeader
        titulo="Reportes"
        descripcion={titulo}
      />
      <BotonExportarFlotante
        onClick={() => void exportar()}
        disabled={exportando || total === 0}
        title="Exportar reporte a Excel"
      >
        <Download className="h-4 w-4 shrink-0" aria-hidden />
        {exportando ? "Exportando…" : "Exportar Excel"}
      </BotonExportarFlotante>
      {error ? <Alert tono="error">{error}</Alert> : null}
      {aviso ? <Alert tono="aviso">{aviso}</Alert> : null}

      <Filters>
        <Field etiqueta="Reporte">
          <select className={inputClass} value={tipo} onChange={(e) => setTipo(e.target.value)}>
            {TIPOS_REPORTE.map((op) => (
              <option key={op.id} value={op.id}>
                {op.etiqueta}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Proceso">
          <select
            className={inputClass}
            value={procesoId}
            onChange={(e) => {
              setProcesoId(e.target.value);
              setProyecto("");
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
            <select className={inputClass} value={proyecto} onChange={(e) => setProyecto(e.target.value)}>
              <option value="">Todos</option>
              {opciones.proyectos.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </select>
          </Field>
        ) : null}
        {muestraPeriodo ? (
          <>
            <Field etiqueta="Período">
              <select
                className={inputClass}
                value={tipoPeriodo}
                onChange={(e) => {
                  const t = e.target.value as TipoPeriodoDashboard;
                  setTipoPeriodo(t);
                  aplicarPeriodo(t, anio, mes, trimestre, semestre);
                }}
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
                value={anio}
                onChange={(e) => {
                  const a = Number(e.target.value);
                  setAnio(a);
                  aplicarPeriodo(tipoPeriodo, a, mes, trimestre, semestre);
                }}
              >
                {aniosDisponibles().map((a) => (
                  <option key={a} value={a}>
                    {a}
                  </option>
                ))}
              </select>
            </Field>
            {tipoPeriodo === "mensual" ? (
              <Field etiqueta="Mes">
                <select
                  className={inputClass}
                  value={mes}
                  onChange={(e) => {
                    const m = Number(e.target.value);
                    setMes(m);
                    aplicarPeriodo(tipoPeriodo, anio, m, trimestre, semestre);
                  }}
                >
                  {MESES.map((nombre, idx) => (
                    <option key={nombre} value={idx + 1}>
                      {nombre}
                    </option>
                  ))}
                </select>
              </Field>
            ) : null}
            {tipoPeriodo === "trimestral" ? (
              <Field etiqueta="Trimestre">
                <select
                  className={inputClass}
                  value={trimestre}
                  onChange={(e) => {
                    const t = Number(e.target.value);
                    setTrimestre(t);
                    aplicarPeriodo(tipoPeriodo, anio, mes, t, semestre);
                  }}
                >
                  <option value={1}>1 (ene–mar)</option>
                  <option value={2}>2 (abr–jun)</option>
                  <option value={3}>3 (jul–sep)</option>
                  <option value={4}>4 (oct–dic)</option>
                </select>
              </Field>
            ) : null}
            {tipoPeriodo === "semestral" ? (
              <Field etiqueta="Semestre">
                <select
                  className={inputClass}
                  value={semestre}
                  onChange={(e) => {
                    const s = Number(e.target.value);
                    setSemestre(s);
                    aplicarPeriodo(tipoPeriodo, anio, mes, trimestre, s);
                  }}
                >
                  <option value={1}>1 (ene–jun)</option>
                  <option value={2}>2 (jul–dic)</option>
                </select>
              </Field>
            ) : null}
          </>
        ) : null}
        {esHistorial ? (
          <Field etiqueta="Trabajador">
            <input
              className={inputClass}
              value={consultaTrabajador}
              onChange={(e) => {
                setConsultaTrabajador(e.target.value);
                if (personaId) setPersonaId("");
              }}
              placeholder="Documento o nombre"
            />
            {sugerencias.length > 0 ? (
              <ul className="mt-1 max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white text-sm shadow-sm">
                {sugerencias.map((p) => (
                  <li key={p.persona_id}>
                    <button
                      type="button"
                      className="w-full px-3 py-2 text-left hover:bg-slate-50"
                      onClick={() => {
                        setPersonaId(String(p.persona_id));
                        setConsultaTrabajador(`${p.numero_documento} — ${p.nombre_completo}`);
                        setSugerencias([]);
                      }}
                    >
                      {p.numero_documento} — {p.nombre_completo}{" "}
                      <Badge tono={p.estado === "Activo" ? "ok" : "aviso"}>{p.estado}</Badge>
                    </button>
                  </li>
                ))}
              </ul>
            ) : null}
          </Field>
        ) : (
          <Field etiqueta="Buscar">
            <input
              className={inputClass}
              value={buscar}
              onChange={(e) => setBuscar(e.target.value)}
              placeholder="Trabajador, documento o capacitación"
            />
          </Field>
        )}
        {muestraEstado ? (
          <Field etiqueta="Estado">
            <select className={inputClass} value={estado} onChange={(e) => setEstado(e.target.value)}>
              <option value="">Todos</option>
              {ESTADOS.map((e) => (
                <option key={e} value={e}>
                  {etiquetaEstado(e)}
                </option>
              ))}
            </select>
          </Field>
        ) : null}
      </Filters>

      <MasFiltros
        abierto={masFiltros}
        onToggle={() => setMasFiltros((abierto) => !abierto)}
        extrasActivos={
          [
            muestraPeriodo ? desde : "",
            muestraPeriodo ? hasta : "",
            esHistorial ? cargoId : "",
            esHistorial ? tipoCapId : "",
            esHistorial ? capacitacionId : "",
          ].filter(Boolean).length
        }
      >
        {muestraPeriodo ? (
          <>
            <Field etiqueta="Fecha inicial">
              <input className={inputClass} type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
            </Field>
            <Field etiqueta="Fecha final">
              <input className={inputClass} type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
            </Field>
          </>
        ) : null}
        {esHistorial ? (
          <>
            <Field etiqueta="Cargo">
              <select className={inputClass} value={cargoId} onChange={(e) => setCargoId(e.target.value)}>
                <option value="">Todos</option>
                {opciones.cargos.map((c) => (
                  <option key={c.cargo_id} value={c.cargo_id}>
                    {c.nombre_cargo}
                  </option>
                ))}
              </select>
            </Field>
            <Field etiqueta="Tipo de capacitación">
              <select className={inputClass} value={tipoCapId} onChange={(e) => setTipoCapId(e.target.value)}>
                <option value="">Todos</option>
                {(opciones.tipos_capacitacion ?? []).map((t) => (
                  <option key={t.tipo_capacitacion_id} value={t.tipo_capacitacion_id}>
                    {t.nombre}
                  </option>
                ))}
              </select>
            </Field>
            <Field etiqueta="Capacitación">
              <select
                className={inputClass}
                value={capacitacionId}
                onChange={(e) => setCapacitacionId(e.target.value)}
              >
                <option value="">Todas</option>
                {(opciones.capacitaciones ?? []).map((c) => (
                  <option key={c.capacitacion_id} value={c.capacitacion_id}>
                    {c.codigo} — {c.nombre}
                  </option>
                ))}
              </select>
            </Field>
          </>
        ) : null}
      </MasFiltros>

      {Object.keys(etiquetas).length > 0 ? (
        <p className="mb-4 text-sm text-slate-600">
          <span className="font-medium text-slate-700">Filtros aplicados:</span>{" "}
          {Object.entries(etiquetas)
            .map(([k, v]) => `${k}: ${v}`)
            .join(" · ")}
        </p>
      ) : null}

      {totales ? (
        <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {tipo === "horas" ? (
            <>
              <Tarjeta etiqueta="Registros" valor={String(totales.asignadas)} />
              <Tarjeta etiqueta="Total horas" valor={totales.horas.toFixed(2)} />
            </>
          ) : tipo === "evidencias_faltantes" || tipo === "proximas" ? (
            <Tarjeta etiqueta="Registros" valor={String(totales.asignadas)} />
          ) : (
            <>
              <Tarjeta etiqueta="Programadas" valor={String(valorProgramadas(totales))} />
              <Tarjeta etiqueta="Ejecutadas" valor={String(valorEjecutadas(totales))} />
              <Tarjeta etiqueta="Pendientes" valor={String(totales.pendientes)} />
              <Tarjeta etiqueta="Fuera de plazo" valor={String(totales.vencidas)} />
              <Tarjeta
                etiqueta="Fuera de tiempo"
                valor={String(totales.ejecutadas_fuera_de_tiempo ?? 0)}
              />
              <Tarjeta
                etiqueta="% cumplimiento"
                valor={totales.porcentaje === null ? "—" : `${totales.porcentaje}%`}
              />
            </>
          )}
        </div>
      ) : null}

      {esHistorial ? (
        <>
          {trabajador ? <FichaTrabajador trabajador={trabajador} /> : null}
          {trabajador ? (
            <div className="mb-4 grid gap-3 lg:grid-cols-3">
              <ListaPeriodos titulo="Historial de cargo" periodos={historialCargo} campo="cargo" />
              <ListaPeriodos titulo="Historial de proceso" periodos={historialProceso} campo="proceso" />
              <ListaPeriodos titulo="Historial de proyectos" periodos={historialProyecto} campo="proyecto" />
            </div>
          ) : null}
          {personaId ? (
            <>
              <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Historial de capacitaciones
              </h2>
              <GruposCapacitacion grupos={grupos} vacio={VACIO} />
            </>
          ) : null}
        </>
      ) : (
        <>
          <Table
            columnas={
              permiteDetalle || permiteDrill
                ? [...columnas, { clave: "_acciones", etiqueta: "Acciones" }]
                : columnas
            }
            filas={items.map((item) => {
              const celdas = columnas.map((col) => celda(tipo, col.clave, item));
              if (permiteDetalle || permiteDrill) {
                celdas.push(
                  <span key="acciones" className="flex flex-wrap gap-2">
                    {permiteDetalle ? (
                      <button
                        type="button"
                        className="font-medium text-hseq-800 underline-offset-2 hover:underline"
                        onClick={() => void abrirDetalle(item)}
                      >
                        Ver detalle
                      </button>
                    ) : null}
                    {permiteDrill ? (
                      <button
                        type="button"
                        className="font-medium text-hseq-800 underline-offset-2 hover:underline"
                        onClick={() => void abrirDrill(item)}
                      >
                        Ver registros
                      </button>
                    ) : null}
                  </span>,
                );
              }
              return celdas;
            })}
            vacio={VACIO}
          />
          <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />
        </>
      )}

      <Modal
        abierto={drillAbierto}
        titulo={drillTitulo}
        onCerrar={() => {
          setDrillAbierto(false);
          setDrillItems([]);
        }}
      >
        {cargandoDrill ? (
          <p className="text-sm text-slate-500">Cargando registros…</p>
        ) : (
          <Table
            columnas={[
              { clave: "documento", etiqueta: "Documento" },
              { clave: "trabajador", etiqueta: "Trabajador" },
              { clave: "capacitacion", etiqueta: "Capacitación" },
              { clave: "fecha_desde", etiqueta: "Desde" },
              { clave: "fecha_hasta", etiqueta: "Hasta" },
              { clave: "fecha_realizacion", etiqueta: "Fecha real" },
              { clave: "oportunidad", etiqueta: "Oportunidad" },
              { clave: "estado", etiqueta: "Estado" },
            ]}
            filas={drillItems.map((item) => [
              texto(item.documento),
              texto(item.trabajador),
              texto(item.capacitacion),
              formatoFecha(item.fecha_desde ?? item.fecha_asignacion),
              formatoFecha(item.fecha_hasta ?? item.fecha_limite_cumplimiento),
              formatoFecha(item.fecha_realizacion),
              texto(item.oportunidad),
              etiquetaEstado(item.estado),
            ])}
            vacio="No hay registros para este grupo en el período."
          />
        )}
      </Modal>

      <Modal abierto={detalle !== null} titulo="Detalle del registro" onCerrar={() => setDetalle(null)}>
        {detalle ? (
          <div className="space-y-4 text-sm">
            <dl className="grid gap-3 sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase text-slate-500">Trabajador</dt>
                <dd className="font-medium text-hseq-900">{texto(detalle.trabajador)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Cédula</dt>
                <dd>{texto(detalle.documento)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Cargo</dt>
                <dd>
                  {texto(detalle.cargo)}
                  {detalle.cargo_id_ext ? (
                    <span className="mt-1 block text-xs text-slate-500">
                      Capacitaciones del cargo según matriz de competencias
                    </span>
                  ) : null}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proceso</dt>
                <dd>{texto(detalle.proceso)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proyecto</dt>
                <dd>{texto(detalle.proyecto)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Capacitación</dt>
                <dd>{texto(detalle.capacitacion)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Estado</dt>
                <dd>{etiquetaEstado(detalle.estado)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Tarea crítica</dt>
                <dd>{detalle.es_tarea_critica ? "Sí" : "No"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Fecha desde</dt>
                <dd>{formatoFecha(detalle.fecha_desde ?? detalle.fecha_asignacion)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Fecha hasta</dt>
                <dd>{formatoFecha(detalle.fecha_hasta ?? detalle.fecha_limite_cumplimiento)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Fecha real de ejecución</dt>
                <dd>{formatoFecha(detalle.fecha_realizacion)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Oportunidad</dt>
                <dd>{texto(detalle.oportunidad)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Vencimiento</dt>
                <dd>{formatoFecha(detalle.fecha_vencimiento)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Resultado / evaluación</dt>
                <dd>
                  {texto(detalle.resultado)}
                  {detalle.nota_evaluacion != null ? ` · Nota ${detalle.nota_evaluacion}` : ""}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Soporte</dt>
                <dd>
                  {cargandoDetalle ? (
                    "Cargando…"
                  ) : soportesDetalle.length > 0 ? (
                    <ul className="space-y-1">
                      {soportesDetalle.map((s) => (
                        <li key={s.soporte_id}>
                          <button
                            type="button"
                            className="font-medium text-hseq-800 underline-offset-2 hover:underline"
                            onClick={() =>
                              void apiDownload(
                                `/api/cumplimientos/soportes/${s.soporte_id}/archivo`,
                                s.nombre_archivo || "soporte",
                              )
                            }
                          >
                            Ver soporte ({s.nombre_archivo})
                          </button>
                        </li>
                      ))}
                    </ul>
                  ) : detalle.requiere_soporte ? (
                    <span className="text-amber-700">Pendiente</span>
                  ) : detalle.tiene_soporte ? (
                    "Sí"
                  ) : (
                    "—"
                  )}
                </dd>
              </div>
            </dl>

            {typeof detalle.persona_id_ext === "number" ? (
              <Link
                prefetch={false}
                href={withQuery("/asignaciones", {
                  persona_id: detalle.persona_id_ext,
                  nombre: typeof detalle.trabajador === "string" ? detalle.trabajador : undefined,
                  documento: typeof detalle.documento === "string" ? detalle.documento : undefined,
                })}
                className="inline-flex font-medium text-hseq-800 underline-offset-2 hover:underline"
              >
                Ver asignaciones del trabajador
              </Link>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </>
  );
}

function Tarjeta({ etiqueta, valor }: { etiqueta: string; valor: string }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
      <p className="text-xs uppercase tracking-wide text-slate-500">{etiqueta}</p>
      <p className="mt-1 text-xl font-semibold text-hseq-900">{valor}</p>
    </div>
  );
}
