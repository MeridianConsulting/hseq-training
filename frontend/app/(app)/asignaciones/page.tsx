"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  FormularioAsignacion,
  type DatosAsignacion,
} from "@/app/(app)/asignaciones/formulario";
import {
  FormularioAsignacionMasiva,
  type DatosAsignacionMasiva,
} from "@/app/(app)/asignaciones/formulario-masivo";
import {
  FormularioCumplimiento,
  type DatosCumplimiento,
} from "@/app/(app)/cumplimientos/formulario";
import { subirSoportes } from "@/app/(app)/cumplimientos/evidencias";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
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
import { BadgeCheck, CalendarPlus, ChevronDown, Eye, Pencil, RefreshCw, Trash2, Users } from "lucide-react";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type {
  Asignacion,
  Capacitacion,
  CargoCorporativo,
  Cumplimiento,
  OpcionesAlertas,
} from "@/lib/tipos";

const FILTROS_DEFAULT = {
  buscar: "",
  capacitacion_id: "",
  estado: "",
  origen: "",
  proceso_id: "",
  proyecto: "",
  cargo_id: "",
  fecha_limite_desde: "",
  fecha_limite_hasta: "",
};

const ETIQUETAS_ESTADO: Record<string, string> = {
  PENDIENTE: "Pendiente",
  PENDIENTE_PROXIMA_A_VENCER: "Próxima a vencer",
  PENDIENTE_VENCIDA: "Pendiente vencida",
  COMPLETADA: "Completada",
  PROXIMA_A_VENCER: "Vigencia próxima a vencer",
  VENCIDA: "Vigencia vencida",
};

function tonoEstado(estado: string) {
  if (estado === "PENDIENTE_VENCIDA" || estado === "VENCIDA") return "alto" as const;
  if (estado === "PENDIENTE_PROXIMA_A_VENCER" || estado === "PROXIMA_A_VENCER") return "aviso" as const;
  if (estado === "COMPLETADA") return "ok" as const;
  return "neutral" as const;
}

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function etiquetaOrigen(origen: string): string {
  if (origen === "AUTOMATICA") return "Automática";
  if (origen === "MANUAL") return "Manual";
  if (origen === "INDUCCION") return "Inducción";
  if (origen === "REINDUCCION") return "Reinducción";
  return origen;
}

function etiquetaCierreCumplimiento(resultado: string | null): string {
  return resultado === "APROBADO" ? "Completado" : "Pendiente";
}

function contextoPersona(item: Asignacion): string {
  const cargo = (item.cargo ?? "").trim();
  const proyecto = (item.proyecto ?? "").trim();
  if (cargo && proyecto) {
    return `${cargo} · ${proyecto}`;
  }
  return cargo || proyecto || "—";
}

function textoObligatoria(valor: boolean | null): string {
  if (valor === null) {
    return "—";
  }
  return valor ? "Sí" : "No";
}

function textoVencimiento(item: Asignacion): string {
  if (item.fecha_vencimiento) {
    return formatoFecha(item.fecha_vencimiento);
  }
  return item.tiene_cumplimiento ? "Sin vencimiento" : "—";
}

export default function AsignacionesPage() {
  return (
    <RequierePermiso permiso="asignaciones.ver">
      <Contenido />
    </RequierePermiso>
  );
}

type OmitidaMasiva = {
  persona_id_ext?: number;
  capacitacion_id?: number;
  motivo: string;
  mensaje?: string;
};

type ResultadoMasivo = {
  seleccionados: number;
  creadas: number;
  omitidas: number;
  errores: number;
  omitidas_detalle?: OmitidaMasiva[];
};

type ResultadoVarias = {
  seleccionados: number;
  creadas: number;
  omitidas: number;
  items: Asignacion[];
  omitidas_detalle?: OmitidaMasiva[];
};

function Contenido() {
  const { puede } = useAuth();
  const { valores, setFiltro, limpiar } = useFiltrosUrl(FILTROS_DEFAULT, {
    keysDebounce: ["buscar"],
  });
  const [items, setItems] = useState<Asignacion[]>([]);
  const [capacitaciones, setCapacitaciones] = useState<Capacitacion[]>([]);
  const [procesos, setProcesos] = useState<{ proceso_id: number; nombre: string }[]>([]);
  const [proyectos, setProyectos] = useState<string[]>([]);
  const [cargos, setCargos] = useState<CargoCorporativo[]>([]);
  const [detalle, setDetalle] = useState<Asignacion | null>(null);
  const [resultadoMasivo, setResultadoMasivo] = useState<ResultadoMasivo | null>(null);
  const [generando, setGenerando] = useState(false);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargandoListado, setCargandoListado] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [masivoAbierto, setMasivoAbierto] = useState(false);
  const [editando, setEditando] = useState<Asignacion | null>(null);
  const [cumpAbierto, setCumpAbierto] = useState(false);
  const [cumpAsignacion, setCumpAsignacion] = useState<Asignacion | null>(null);
  const [enviandoCump, setEnviandoCump] = useState(false);
  const [evidenciaFaltante, setEvidenciaFaltante] = useState(false);
  const [masFiltros, setMasFiltros] = useState(() =>
    Boolean(
      valores.proceso_id ||
        valores.cargo_id ||
        valores.proyecto ||
        valores.fecha_limite_desde ||
        valores.fecha_limite_hasta,
    ),
  );
  const [faltantes, setFaltantes] = useState<Cumplimiento[]>([]);

  async function cargarListado(paginaActual = 1) {
    setCargandoListado(true);
    try {
      const respuesta = await apiGet<ListaPaginada<Asignacion>>(
        withQuery("/api/asignaciones", {
          page: paginaActual,
          per_page: 15,
          buscar: valores.buscar.trim() || undefined,
          capacitacion_id: valores.capacitacion_id || undefined,
          estado: valores.estado || undefined,
          origen: valores.origen || undefined,
          proceso_id: valores.proceso_id || undefined,
          proyecto: valores.proyecto || undefined,
          fecha_limite_desde: valores.fecha_limite_desde || undefined,
          fecha_limite_hasta: valores.fecha_limite_hasta || undefined,
          cargo_id: valores.cargo_id || undefined,
        }),
      );

      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar las asignaciones.");
        return;
      }

      setItems(respuesta.data.items);
      setPagina(respuesta.data.pagination.current_page);
      setUltima(respuesta.data.pagination.last_page);
      setError(null);
    } finally {
      setCargandoListado(false);
    }
  }

  async function refrescar(paginaActual = pagina) {
    await cargarListado(paginaActual);
  }

  useDebouncedCallback(
    () => {
      void cargarListado(1);
    },
    [
      valores.buscar,
      valores.capacitacion_id,
      valores.estado,
      valores.origen,
      valores.proceso_id,
      valores.proyecto,
      valores.fecha_limite_desde,
      valores.fecha_limite_hasta,
      valores.cargo_id,
    ],
  );

  useEffect(() => {
    if (!evidenciaFaltante) {
      setFaltantes([]);
      return;
    }
    const abortado = { actual: false };
    void (async () => {
      const r = await apiGet<ListaPaginada<Cumplimiento>>(
        withQuery("/api/cumplimientos", {
          evidencia_faltante: 1,
          per_page: 50,
        }),
      );
      if (abortado.actual) {
        return;
      }
      if (!r.success || !r.data) {
        setError(r.message || "No fue posible cargar las evidencias faltantes.");
        setFaltantes([]);
        return;
      }
      setFaltantes(r.data.items);
    })();
    return () => {
      abortado.actual = true;
    };
  }, [evidenciaFaltante]);

  useEffect(() => {
    void (async () => {
      const [caps, procs, opts, rCargos] = await Promise.all([
        apiGet<ListaPaginada<Capacitacion>>(
          withQuery("/api/capacitaciones", { per_page: 100, estado: "ACTIVA" }),
        ),
        apiGet<{ items: { proceso_id: number; nombre: string }[] }>(
          "/api/catalogs/procesos?activos=1",
        ),
        apiGet<OpcionesAlertas>("/api/alertas/opciones"),
        apiGet<CargoCorporativo[]>("/api/personal/cargos"),
      ]);
      setCapacitaciones(caps.data?.items ?? []);
      setProcesos(
        (procs.data?.items ?? []).map((p) => ({
          proceso_id: Number(p.proceso_id),
          nombre: String(p.nombre ?? ""),
        })),
      );
      setProyectos(opts.data?.proyectos ?? []);
      if (rCargos.success && rCargos.data) {
        setCargos(rCargos.data);
      }
    })();
  }, []);

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
    if (valores.origen) {
      chips.push({
        clave: "origen",
        etiqueta: "Origen",
        valor: etiquetaOrigen(valores.origen),
      });
    }
    if (valores.estado) {
      chips.push({
        clave: "estado",
        etiqueta: "Estado",
        valor: ETIQUETAS_ESTADO[valores.estado] ?? valores.estado,
      });
    }
    if (valores.proceso_id) {
      const proc = procesos.find((p) => String(p.proceso_id) === valores.proceso_id);
      chips.push({
        clave: "proceso_id",
        etiqueta: "Proceso",
        valor: proc?.nombre ?? valores.proceso_id,
      });
    }
    if (valores.proyecto) {
      chips.push({ clave: "proyecto", etiqueta: "Proyecto", valor: valores.proyecto });
    }
    if (valores.cargo_id) {
      const cargo = cargos.find((c) => String(c.cargo_id) === valores.cargo_id);
      chips.push({
        clave: "cargo_id",
        etiqueta: "Cargo",
        valor: cargo?.nombre_cargo ?? valores.cargo_id,
      });
    }
    if (valores.fecha_limite_desde) {
      chips.push({
        clave: "fecha_limite_desde",
        etiqueta: "Fecha límite desde",
        valor: formatoFecha(valores.fecha_limite_desde),
      });
    }
    if (valores.fecha_limite_hasta) {
      chips.push({
        clave: "fecha_limite_hasta",
        etiqueta: "Fecha límite hasta",
        valor: formatoFecha(valores.fecha_limite_hasta),
      });
    }
    if (evidenciaFaltante) {
      chips.push({
        clave: "evidencia_faltante",
        etiqueta: "Soportes",
        valor: "Sin soporte",
      });
    }
    return chips;
  }, [valores, capacitaciones, procesos, cargos, evidenciaFaltante]);

  const extrasActivos = [
    valores.proceso_id,
    valores.cargo_id,
    valores.proyecto,
    valores.fecha_limite_desde,
    valores.fecha_limite_hasta,
    evidenciaFaltante ? "1" : "",
  ].filter(Boolean).length;

  function quitarChip(clave: string) {
    if (clave === "evidencia_faltante") {
      setEvidenciaFaltante(false);
      return;
    }
    setFiltro(clave, "");
  }

  function limpiarFiltros() {
    limpiar();
    setEvidenciaFaltante(false);
  }
  async function guardar(evento: FormEvent, datos: DatosAsignacion) {
    evento.preventDefault();
    setError(null);

    if (editando) {
      const respuesta = await apiPut<Asignacion>(`/api/asignaciones/${editando.asignacion_id}`, {
        fecha_limite_cumplimiento: datos.fecha_limite_cumplimiento,
      });
      if (!respuesta.success) {
        setError(respuesta.message || "No se pudo actualizar la fecha.");
        return;
      }
      setMensaje(respuesta.message);
    } else {
      if (!datos.persona_id_ext || datos.capacitacion_ids.length < 1) {
        setError("Seleccione trabajador y al menos una capacitación aplicable.");
        return;
      }
      const ids = datos.capacitacion_ids.map(Number);
      const respuesta = ids.length === 1
        ? await apiPost<Asignacion>("/api/asignaciones", {
            persona_id_ext: Number(datos.persona_id_ext),
            capacitacion_id: ids[0],
            fecha_limite_cumplimiento: datos.fecha_limite_cumplimiento,
            fecha_asignacion: datos.fecha_asignacion || undefined,
          })
        : await apiPost<ResultadoVarias>("/api/asignaciones", {
            persona_id_ext: Number(datos.persona_id_ext),
            capacitacion_ids: ids,
            fecha_limite_cumplimiento: datos.fecha_limite_cumplimiento,
            fecha_asignacion: datos.fecha_asignacion || undefined,
          });
      if (!respuesta.success) {
        setError(respuesta.message || "No fue posible crear la asignación.");
        return;
      }
      setMensaje(respuesta.message);
      const lote = respuesta.data as ResultadoVarias | Asignacion | undefined;
      if (lote && "omitidas_detalle" in lote && (lote.omitidas_detalle?.length ?? 0) > 0) {
        const extra = lote.omitidas_detalle
          ?.map((o) => o.mensaje || o.motivo)
          .filter(Boolean)
          .join(" ");
        if (extra) {
          setError(extra);
        }
      }
    }

    setAbierto(false);
    setEditando(null);
    await refrescar(1);
  }

  async function guardarMasivo(evento: FormEvent, datos: DatosAsignacionMasiva) {
    evento.preventDefault();
    const n = datos.persona_ids_ext.length;
    if (
      !confirm(
        `¿Desea asignar esta capacitación a los ${n} trabajador${n === 1 ? "" : "es"} seleccionado${n === 1 ? "" : "s"}?`,
      )
    ) {
      return;
    }

    const respuesta = await apiPost<ResultadoMasivo>("/api/asignaciones/masivo", {
      capacitacion_id: Number(datos.capacitacion_id),
      persona_ids_ext: datos.persona_ids_ext.map(Number),
      fecha_limite_cumplimiento: datos.fecha_limite_cumplimiento || undefined,
    });

    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo completar la asignación masiva.");
      return;
    }

    setMensaje(respuesta.message);
    setError(null);
    setResultadoMasivo(respuesta.data ?? null);
    await refrescar(1);
  }

  async function generarAutomaticas() {
    if (
      !confirm(
        "¿Generar las asignaciones automáticas pendientes según la matriz para los trabajadores activos?",
      )
    ) {
      return;
    }
    setGenerando(true);
    const respuesta = await apiPost<{ creadas: number; omitidas: number }>(
      "/api/asignaciones/generar-automaticas",
      {},
    );
    setGenerando(false);
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible generar las asignaciones automáticas.");
      return;
    }
    setMensaje(respuesta.message);
    setError(null);
    await refrescar(1);
  }

  function etiquetaOmitida(item: OmitidaMasiva): string {
    if (item.mensaje) {
      return item.mensaje;
    }
    if (item.motivo === "no_aplicable") {
      return "No aplicable según matriz";
    }
    if (item.motivo === "duplicado") {
      return "Ya tiene esta capacitación asignada";
    }
    if (item.motivo === "inactivo") {
      return "Trabajador inactivo";
    }
    return item.motivo;
  }

  function sesionDeCumplimiento(item: Asignacion): number {
    return item.cumplimiento_sesion_id && item.cumplimiento_sesion_id > 0
      ? item.cumplimiento_sesion_id
      : 0;
  }

  async function guardarCumplimiento(evento: FormEvent, datos: DatosCumplimiento) {
    evento.preventDefault();
    if (!cumpAsignacion) {
      return;
    }
    const sesionId = sesionDeCumplimiento(cumpAsignacion);
    if (sesionId < 1) {
      setError("No hay una sesión con asistencia para esta asignación.");
      return;
    }
    setEnviandoCump(true);
    const cumpId = cumpAsignacion.cumplimiento_id;
    if (datos.archivos.length > 0) {
      if (!cumpId) {
        setEnviandoCump(false);
        setError("No hay un cumplimiento borrador para adjuntar el archivo.");
        return;
      }
      const err = await subirSoportes(cumpId, datos.archivos);
      if (err) {
        setEnviandoCump(false);
        setError(err);
        return;
      }
    }
    const respuesta = await apiPost<Cumplimiento>("/api/cumplimientos", {
      asignacion_id: cumpAsignacion.asignacion_id,
      sesion_id: sesionId,
      fecha_realizacion: datos.fecha_realizacion,
      resultado: datos.resultado,
      horas_efectivas: Number(datos.horas_efectivas),
      observaciones: datos.observaciones.trim() || null,
      nota_evaluacion:
        datos.nota_evaluacion.trim() === "" ? undefined : Number(datos.nota_evaluacion),
    });
    setEnviandoCump(false);
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible registrar el cumplimiento.");
      return;
    }
    setMensaje(respuesta.message);
    setCumpAbierto(false);
    setCumpAsignacion(null);
    await refrescar(pagina);
  }

  async function eliminar(item: Asignacion) {
    if (!confirm("¿Eliminar esta asignación?")) {
      return;
    }
    const respuesta = await apiDelete(`/api/asignaciones/${item.asignacion_id}`);
    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo eliminar.");
      return;
    }
    setMensaje(respuesta.message);
    await refrescar();
  }

  async function verDetalle(item: Asignacion) {
    const respuesta = await apiGet<Asignacion>(`/api/asignaciones/${item.asignacion_id}`);
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible consultar la asignación.");
      return;
    }
    setDetalle(respuesta.data);
  }

  return (
    <>
      <PageHeader
        titulo="Asignaciones"
        descripcion="Defina qué capacitaciones debe cumplir cada trabajador según la matriz de aplicabilidad."
        acciones={
          puede("asignaciones.crear") ? (
            <span className="flex flex-wrap gap-2">
              <Button
                type="button"
                variante="secondary"
                disabled={generando}
                onClick={() => void generarAutomaticas()}
              >
                <RefreshCw className="h-4 w-4" aria-hidden />
                {generando ? "Generando…" : "Generar automáticas"}
              </Button>
              <Button
                type="button"
                variante="secondary"
                onClick={() => {
                  setResultadoMasivo(null);
                  setMasivoAbierto(true);
                }}
              >
                <Users className="h-4 w-4" aria-hidden />
                Asignación masiva
              </Button>
              <Button
                type="button"
                onClick={() => {
                  setEditando(null);
                  setAbierto(true);
                }}
              >
                <CalendarPlus className="h-4 w-4" aria-hidden />
                Asignar capacitación
              </Button>
            </span>
          ) : null
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

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
            <option value="PENDIENTE">Pendiente</option>
            <option value="PENDIENTE_PROXIMA_A_VENCER">Próxima a vencer</option>
            <option value="PENDIENTE_VENCIDA">Pendiente vencida</option>
            <option value="COMPLETADA">Completada</option>
            <option value="PROXIMA_A_VENCER">Vigencia próxima a vencer</option>
            <option value="VENCIDA">Vigencia vencida</option>
          </select>
        </Field>
        <Field etiqueta="Origen">
          <select
            className={inputClass}
            value={valores.origen}
            onChange={(e) => setFiltro("origen", e.target.value)}
          >
            <option value="">Todos</option>
            <option value="AUTOMATICA">Automática</option>
            <option value="MANUAL">Manual</option>
            <option value="INDUCCION">Inducción</option>
            <option value="REINDUCCION">Reinducción</option>
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
              <Field etiqueta="Proceso">
                <select
                  className={inputClass}
                  value={valores.proceso_id}
                  onChange={(e) => setFiltro("proceso_id", e.target.value)}
                >
                  <option value="">Todos</option>
                  {procesos.map((p) => (
                    <option key={p.proceso_id} value={p.proceso_id}>
                      {p.nombre}
                    </option>
                  ))}
                </select>
              </Field>
              <Field etiqueta="Cargo">
                <select
                  className={inputClass}
                  value={valores.cargo_id}
                  onChange={(e) => setFiltro("cargo_id", e.target.value)}
                >
                  <option value="">Todos</option>
                  {cargos.map((c) => (
                    <option key={c.cargo_id} value={c.cargo_id}>
                      {c.nombre_cargo}
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
                  {proyectos.map((nombre) => (
                    <option key={nombre} value={nombre}>
                      {nombre}
                    </option>
                  ))}
                </select>
              </Field>
              <Field etiqueta="Fecha límite desde">
                <input
                  type="date"
                  className={inputClass}
                  value={valores.fecha_limite_desde}
                  onChange={(e) => setFiltro("fecha_limite_desde", e.target.value)}
                />
              </Field>
              <Field etiqueta="Fecha límite hasta">
                <input
                  type="date"
                  className={inputClass}
                  value={valores.fecha_limite_hasta}
                  onChange={(e) => setFiltro("fecha_limite_hasta", e.target.value)}
                />
              </Field>
              <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={evidenciaFaltante}
                  onChange={(e) => setEvidenciaFaltante(e.target.checked)}
                />
                Ver cumplimientos sin soporte
              </label>
          </div>
        ) : null}
      </div>

      <FiltrosActivos chips={chipsActivos} onQuitar={quitarChip} onLimpiar={limpiarFiltros} />

      {evidenciaFaltante ? (
        <Card className="mb-6">
          <h2 className="mb-3 text-sm font-semibold text-hseq-900">Cumplimientos sin soporte</h2>
          <Table
            columnas={[
              { clave: "persona", etiqueta: "Trabajador" },
              { clave: "doc", etiqueta: "Documento" },
              { clave: "cap", etiqueta: "Capacitación" },
              { clave: "fecha", etiqueta: "Fecha de realización" },
              { clave: "estado", etiqueta: "Estado" },
              { clave: "cert", etiqueta: "Requiere certificado" },
              { clave: "cant", etiqueta: "Cantidad de evidencias" },
            ]}
            filas={faltantes.map((c) => [
              c.persona_nombre ?? `Persona ${c.persona_id_ext}`,
              c.numero_documento ?? "—",
              c.capacitacion_codigo
                ? `${c.capacitacion_codigo} — ${c.capacitacion_nombre}`
                : (c.capacitacion_nombre ?? "—"),
              formatoFecha(c.fecha_realizacion),
              etiquetaCierreCumplimiento(c.resultado),
              "Sí",
              c.soportes_count ?? 0,
            ])}
            vacio="No hay cumplimientos sin soporte."
          />
        </Card>
      ) : null}

      {cargandoListado ? (
        <ListaCargando mensaje="Cargando asignaciones…" />
      ) : (
        <>
          <Table
            columnas={[
              { clave: "persona", etiqueta: "Trabajador" },
              { clave: "cap", etiqueta: "Capacitación" },
              { clave: "contexto", etiqueta: "Cargo / proyecto" },
              { clave: "limite", etiqueta: "Fecha límite" },
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
              <span key="c" className="flex flex-col">
                <span>{item.capacitacion_nombre}</span>
                <span className="text-xs text-slate-500">{item.capacitacion_codigo}</span>
              </span>,
              contextoPersona(item),
              formatoFecha(item.fecha_limite_cumplimiento),
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
                {puede("cumplimientos.crear") &&
                item.cumplimiento_resultado !== "APROBADO" &&
                sesionDeCumplimiento(item) > 0 ? (
                  <Button
                    type="button"
                    variante="ghost"
                    className="px-2"
                    title="Registrar cumplimiento"
                    aria-label="Registrar cumplimiento"
                    onClick={() => {
                      setCumpAsignacion(item);
                      setCumpAbierto(true);
                    }}
                  >
                    <BadgeCheck className="h-4 w-4" aria-hidden />
                  </Button>
                ) : null}
                {puede("asignaciones.editar") ? (
                  <Button
                    type="button"
                    variante="ghost"
                    className="px-2"
                    title="Actualizar fecha"
                    aria-label="Actualizar fecha límite"
                    onClick={() => {
                      setEditando(item);
                      setAbierto(true);
                    }}
                  >
                    <Pencil className="h-4 w-4" aria-hidden />
                  </Button>
                ) : null}
                {puede("asignaciones.eliminar") && !item.tiene_cumplimiento ? (
                  <Button
                    type="button"
                    variante="danger"
                    className="px-2"
                    title="Eliminar"
                    aria-label="Eliminar asignación"
                    onClick={() => void eliminar(item)}
                  >
                    <Trash2 className="h-4 w-4" aria-hidden />
                  </Button>
                ) : null}
              </span>,
            ])}
            vacio="No hay asignaciones para los filtros seleccionados."
          />
          <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargarListado(p)} />
        </>
      )}

      <Modal
        abierto={abierto}
        titulo={editando ? "Actualizar fecha límite" : "Asignar capacitación"}
        onCerrar={() => {
          setAbierto(false);
          setEditando(null);
        }}
      >
        <FormularioAsignacion
          key={editando ? String(editando.asignacion_id) : "nueva"}
          inicial={editando}
          capacitaciones={capacitaciones}
          soloFecha={Boolean(editando)}
          onSubmit={guardar}
          onCancelar={() => {
            setAbierto(false);
            setEditando(null);
          }}
        />
      </Modal>

      <Modal
        abierto={masivoAbierto}
        titulo="Asignación masiva"
        onCerrar={() => setMasivoAbierto(false)}
      >
        <FormularioAsignacionMasiva
          key={masivoAbierto ? "masivo-abierto" : "masivo-cerrado"}
          capacitaciones={capacitaciones}
          onCancelar={() => setMasivoAbierto(false)}
          onGuardar={guardarMasivo}
        />
        {resultadoMasivo ? (
          <div className="mt-4 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
            <p>
              Personas seleccionadas: {resultadoMasivo.seleccionados}. Creadas:{" "}
              {resultadoMasivo.creadas}. Omitidas: {resultadoMasivo.omitidas}.
            </p>
            {(resultadoMasivo.omitidas_detalle ?? []).length > 0 ? (
              <ul className="list-disc space-y-1 pl-5 text-slate-700">
                {(resultadoMasivo.omitidas_detalle ?? []).map((fila, i) => (
                  <li key={`${fila.persona_id_ext ?? "x"}-${i}`}>
                    Trabajador {fila.persona_id_ext ?? "—"}: {etiquetaOmitida(fila)}
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        ) : null}
      </Modal>

      <Modal
        abierto={detalle !== null}
        titulo="Detalle de la asignación"
        onCerrar={() => setDetalle(null)}
      >
        {detalle ? (
          <dl className="grid gap-3 sm:grid-cols-2 text-sm">
            <div>
              <dt className="text-xs uppercase text-slate-500">Trabajador</dt>
              <dd>
                <Link className="text-hseq-800 underline" href={`/personal/${detalle.persona_id_ext}`}>
                  {detalle.persona_nombre ?? detalle.persona_id_ext}
                </Link>
              </dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Documento</dt>
              <dd>{detalle.numero_documento ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Cargo</dt>
              <dd>{detalle.cargo ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Proyecto</dt>
              <dd>{detalle.proyecto ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Capacitación</dt>
              <dd>
                {detalle.capacitacion_codigo} — {detalle.capacitacion_nombre}
              </dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Origen</dt>
              <dd>{etiquetaOrigen(detalle.origen)}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Vigencia / periodicidad</dt>
              <dd>{detalle.periodicidad_nombre ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Obligatoria</dt>
              <dd>{textoObligatoria(detalle.obligatoria)}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Estado</dt>
              <dd>
                <Badge tono={tonoEstado(detalle.estado_calculado)}>
                  {ETIQUETAS_ESTADO[detalle.estado_calculado] ?? detalle.estado_calculado}
                </Badge>
                {detalle.etiqueta_dias ? (
                  <span className="ml-2 text-slate-600">{detalle.etiqueta_dias}</span>
                ) : null}
              </dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Fecha de asignación</dt>
              <dd>{formatoFecha(detalle.fecha_asignacion)}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Fecha límite</dt>
              <dd>{formatoFecha(detalle.fecha_limite_cumplimiento)}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Cumplimiento</dt>
              <dd>
                {detalle.tiene_cumplimiento
                  ? `${detalle.cumplimiento_resultado === "APROBADO" ? "Aprobado" : (detalle.cumplimiento_resultado ?? "Registrado")} · ${formatoFecha(detalle.fecha_realizacion)}`
                  : "Sin ejecución"}
              </dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Horas</dt>
              <dd>{detalle.horas_efectivas ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-slate-500">Vencimiento</dt>
              <dd>{textoVencimiento(detalle)}</dd>
            </div>
          </dl>
        ) : null}
      </Modal>

      <Modal
        abierto={cumpAbierto}
        titulo="Registrar cumplimiento"
        onCerrar={() => {
          setCumpAbierto(false);
          setCumpAsignacion(null);
        }}
      >
        {cumpAsignacion ? (
          <FormularioCumplimiento
            key={cumpAsignacion.asignacion_id}
            asignacionId={cumpAsignacion.asignacion_id}
            sesionId={sesionDeCumplimiento(cumpAsignacion)}
            cumplimientoId={cumpAsignacion.cumplimiento_id}
            fechaDefault={(cumpAsignacion.fecha_realizacion ?? "").slice(0, 10)}
            enviando={enviandoCump}
            onError={setError}
            onSoporteEliminado={() => void refrescar(pagina)}
            onCancelar={() => {
              setCumpAbierto(false);
              setCumpAsignacion(null);
            }}
            onSubmit={guardarCumplimiento}
          />
        ) : null}
      </Modal>
    </>
  );
}
