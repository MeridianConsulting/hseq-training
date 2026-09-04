"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { Download } from "lucide-react";
import { FichaTrabajador, GruposCapacitacion, ListaPeriodos } from "./historial";
import { apiDownload, apiGet, withQuery } from "@/lib/api";
import type {
  FichaTrabajadorReporte,
  GrupoHistorial,
  OpcionesAlertas,
  PeriodoHistorial,
  PersonaCorporativa,
  ResultadoReporte,
  SoporteCumplimiento,
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
  "inducciones",
  "reinducciones",
  "tareas_criticas",
];

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

function procesoPermiteProyecto(
  procesoId: string,
  procesos: OpcionesAlertas["procesos"],
): boolean {
  const seleccionado = procesos.find((p) => String(p.proceso_id) === procesoId);
  if (!seleccionado) return false;
  const n = seleccionado.nombre
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
  return n.includes("gestion de proyectos");
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
      { clave: "vencidas", etiqueta: "Vencidas" },
      { clave: "porcentaje", etiqueta: "% cumplimiento" },
    ];
  }
  if (tipo === "cumplimiento_cargo" || tipo === "cumplimiento_proceso" || tipo === "cumplimiento_proyecto") {
    const grupo =
      tipo === "cumplimiento_cargo" ? "Cargo" : tipo === "cumplimiento_proceso" ? "Proceso" : "Proyecto";
    return [
      { clave: "grupo", etiqueta: grupo },
      { clave: "programadas", etiqueta: "Programadas" },
      { clave: "ejecutadas", etiqueta: "Ejecutadas" },
      { clave: "pendientes", etiqueta: "Pendientes" },
      { clave: "vencidas", etiqueta: "Vencidas" },
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
  if (tipo === "asistencia") {
    return [
      { clave: "documento", etiqueta: "Documento" },
      { clave: "trabajador", etiqueta: "Trabajador" },
      { clave: "capacitacion", etiqueta: "Capacitación" },
      { clave: "fecha", etiqueta: "Fecha" },
      { clave: "hora", etiqueta: "Hora" },
      { clave: "modalidad", etiqueta: "Modalidad" },
      { clave: "estado_asistencia", etiqueta: "Asistencia" },
      { clave: "motivo_ausencia", etiqueta: "Motivo" },
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
    { clave: "fecha_asignacion", etiqueta: "Asignación" },
    { clave: "fecha_realizacion", etiqueta: "Realización" },
    { clave: "fecha_vencimiento", etiqueta: "Vencimiento" },
  ];
  if (tipo === "inducciones") {
    cols.splice(2, 0, { clave: "fecha_ingreso", etiqueta: "Ingreso" });
  }
  if (tipo === "reinducciones") {
    cols.push({ clave: "periodicidad", etiqueta: "Periodicidad" });
  }
  if (tipo === "tareas_criticas" || tipo === "cumplimiento_general") {
    cols.push({ clave: "es_tarea_critica", etiqueta: "Crítica" });
  }
  return cols;
}

function celda(tipo: string, clave: string, item: Record<string, unknown>) {
  const valor = item[clave];
  if (clave.includes("fecha") || clave === "fecha") return formatoFecha(valor);
  if (clave === "estado" || clave === "estado_asistencia" || clave === "origen") return etiquetaEstado(valor);
  if (clave === "porcentaje") return valor === null || valor === undefined ? "—" : `${valor}%`;
  if (clave === "es_tarea_critica" || clave === "tiene_soporte" || clave === "requiere_certificado") {
    return valor ? "Sí" : "No";
  }
  if (clave === "trabajador" && item.persona_id_ext) {
    return (
      <Link
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
  const [tipo, setTipo] = useState("cumplimiento_general");
  const [desde, setDesde] = useState("");
  const [hasta, setHasta] = useState("");
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

  const esHistorial = tipo === "historial_trabajador";
  const muestraProyecto = procesoPermiteProyecto(procesoId, opciones.procesos);
  const permiteDetalle = TIPOS_DETALLE.includes(tipo);

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
    "inducciones",
    "reinducciones",
    "historial_trabajador",
  ].includes(tipo);
  const muestraPeriodo = tipo !== "proximas";

  return (
    <>
      <PageHeader
        titulo="Reportes"
        descripcion={titulo}
        acciones={
          <Button onClick={() => void exportar()} disabled={exportando || total === 0}>
            <Download className="h-4 w-4" aria-hidden />
            {exportando ? "Exportando…" : "Exportar Excel"}
          </Button>
        }
      />
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
            <Field etiqueta="Fecha inicial">
              <input className={inputClass} type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
            </Field>
            <Field etiqueta="Fecha final">
              <input className={inputClass} type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
            </Field>
          </>
        ) : null}
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
      </Filters>

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
          ) : tipo === "asistencia" ? (
            <>
              <Tarjeta etiqueta="Registros" valor={String(totales.asignadas)} />
              <Tarjeta etiqueta="Asistieron" valor={String(totales.asistieron ?? 0)} />
              <Tarjeta etiqueta="Tarde" valor={String(totales.tarde ?? 0)} />
              <Tarjeta etiqueta="Ausentes" valor={String(totales.ausentes ?? 0)} />
            </>
          ) : tipo === "evidencias_faltantes" || tipo === "proximas" ? (
            <Tarjeta etiqueta="Registros" valor={String(totales.asignadas)} />
          ) : (
            <>
              <Tarjeta etiqueta="Programadas" valor={String(valorProgramadas(totales))} />
              <Tarjeta etiqueta="Ejecutadas" valor={String(valorEjecutadas(totales))} />
              <Tarjeta etiqueta="Pendientes" valor={String(totales.pendientes)} />
              <Tarjeta etiqueta="Vencidas" valor={String(totales.vencidas)} />
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
              permiteDetalle
                ? [...columnas, { clave: "_acciones", etiqueta: "Acciones" }]
                : columnas
            }
            filas={items.map((item) => {
              const celdas = columnas.map((col) => celda(tipo, col.clave, item));
              if (permiteDetalle) {
                celdas.push(
                  <button
                    key="detalle"
                    type="button"
                    className="font-medium text-hseq-800 underline-offset-2 hover:underline"
                    onClick={() => void abrirDetalle(item)}
                  >
                    Ver detalle
                  </button>,
                );
              }
              return celdas;
            })}
            vacio={VACIO}
          />
          <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />
        </>
      )}

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
                <dt className="text-xs uppercase text-slate-500">Asignación</dt>
                <dd>{formatoFecha(detalle.fecha_asignacion)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Realización</dt>
                <dd>{formatoFecha(detalle.fecha_realizacion)}</dd>
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
