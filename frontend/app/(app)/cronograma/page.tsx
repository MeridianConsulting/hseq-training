"use client";

import { useEffect, useState } from "react";
import { PanelOperativo } from "@/app/(app)/cronograma/panel-operativo";
import {
  FormularioSesion,
  tipoModalidad,
} from "@/app/(app)/cronograma/formulario-sesion";
import { FiltroCronograma, type FiltroCronogramaValor } from "@/components/cronograma/filtro-cronograma";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Table } from "@/components/ui/table";
import { Ban, ClipboardCheck, Eye, Play, Users } from "lucide-react";
import { apiGet, apiPost, withQuery } from "@/lib/api";
import { humanizarNombreUnidad } from "@/lib/catalogos";
import type { ItemCronograma, TableroCronograma, TrabajadorCronograma } from "@/lib/tipos";

function filtroInicial(): FiltroCronogramaValor {
  const hoy = new Date();
  return {
    tipo: "mensual",
    anio: hoy.getFullYear(),
    mes: hoy.getMonth() + 1,
    trimestre: Math.ceil((hoy.getMonth() + 1) / 3),
    semestre: hoy.getMonth() + 1 <= 6 ? 1 : 2,
    procesoId: "",
    proyecto: "",
    buscar: "",
  };
}

function formatearFecha(iso: string | null | undefined): string {
  if (!iso) return "—";
  const partes = iso.slice(0, 10).split("-");
  if (partes.length !== 3) return iso;
  return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function etiquetaEstado(estado: string): string {
  if (estado === "PROGRAMADA") return "Programada";
  if (estado === "EN_EJECUCION") return "En ejecución";
  if (estado === "FINALIZADA") return "Finalizada";
  if (estado === "CANCELADA") return "Cancelada";
  return estado;
}

function tonoEstado(estado: string) {
  if (estado === "FINALIZADA") return "ok" as const;
  if (estado === "EN_EJECUCION") return "aviso" as const;
  if (estado === "CANCELADA") return "alto" as const;
  return "neutral" as const;
}

function etiquetaVigencia(item: ItemCronograma): string {
  return humanizarNombreUnidad(item.vigencia_nombre) || "No vence";
}

function sesionActiva(item: ItemCronograma): number | null {
  const programada = (item.sesiones ?? []).find((s) => s.estado === "PROGRAMADA");
  if (programada) return programada.sesion_id;
  const ultima = (item.sesiones ?? [])[item.sesiones.length - 1];
  return ultima ? ultima.sesion_id : null;
}

/** Virtual/mixta (u otra) requieren formulario con enlace; presencial puede iniciar rápido. */
function requiereFormularioInicio(item: ItemCronograma): boolean {
  return tipoModalidad(item.metodologia) !== "PRESENCIAL";
}

const ETIQUETAS_ASIGNACION: Record<string, string> = {
  PENDIENTE: "Pendiente",
  PENDIENTE_PROXIMA_A_VENCER: "Próxima a vencer",
  PENDIENTE_VENCIDA: "Pendiente vencida",
  PROXIMA_A_VENCER: "Próxima a vencer",
  VENCIDA: "Vencida",
  COMPLETADA: "Completada",
};

export default function CronogramaPage() {
  return (
    <RequierePermiso permiso="planes.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [filtro, setFiltro] = useState<FiltroCronogramaValor>(filtroInicial);
  const [tablero, setTablero] = useState<TableroCronograma | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [recarga, setRecarga] = useState(0);
  const [detalle, setDetalle] = useState<ItemCronograma | null>(null);
  const [trabajadoresDe, setTrabajadoresDe] = useState<ItemCronograma | null>(null);
  const [trabajadores, setTrabajadores] = useState<TrabajadorCronograma[]>([]);
  const [iniciarDe, setIniciarDe] = useState<ItemCronograma | null>(null);
  const [panel, setPanel] = useState<{ item: ItemCronograma; sesionId: number } | null>(null);
  const [guardando, setGuardando] = useState(false);

  useEffect(() => {
    const abortado = { actual: false };

    void (async () => {
      setCargando(true);
      setError(null);

      const params: Record<string, string | number> = {
        tipo: filtro.tipo,
        anio: filtro.anio,
      };
      if (filtro.tipo === "mensual") params.mes = filtro.mes;
      if (filtro.tipo === "trimestral") params.trimestre = filtro.trimestre;
      if (filtro.tipo === "semestral") params.semestre = filtro.semestre;
      if (filtro.procesoId) params.proceso_id = Number(filtro.procesoId);
      if (filtro.proyecto) params.proyecto = filtro.proyecto;
      if (filtro.buscar.trim()) params.buscar = filtro.buscar.trim();

      const respuesta = await apiGet<TableroCronograma>(withQuery("/api/cronograma", params));

      if (abortado.actual) return;

      if (respuesta.cancelada) {
        return;
      }
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar el cronograma.");
        setTablero(null);
        setCargando(false);
        return;
      }

      setTablero(respuesta.data);
      setCargando(false);
    })();

    return () => {
      abortado.actual = true;
    };
  }, [filtro, recarga]);

  const items = tablero?.items ?? [];
  const procesos = tablero?.procesos ?? [];
  const proyectos = tablero?.proyectos ?? [];

  function recargar() {
    setRecarga((n) => n + 1);
  }

  async function abrirTrabajadores(item: ItemCronograma) {
    setTrabajadoresDe(item);
    setTrabajadores([]);
    const respuesta = item.plan_detalle_id
      ? await apiGet<{ items: TrabajadorCronograma[] }>(
          `/api/cronograma/${item.plan_detalle_id}/trabajadores`,
        )
      : await apiGet<{ items: TrabajadorCronograma[] }>(
          withQuery("/api/cronograma/grupo/trabajadores", {
            capacitacion_id: item.capacitacion_id,
            anio: item.anio,
            mes: item.mes,
          }),
        );
    if (respuesta.cancelada) {
      return;
    }
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar los trabajadores.");
      return;
    }
    setTrabajadores(respuesta.data.items ?? []);
  }

  async function cancelarProgramacion(item: ItemCronograma) {
    if (!item.plan_detalle_id) {
      setError("No es posible cancelar desde Cronograma una fila sin vínculo al plan. Ajuste el plazo en Asignaciones.");
      return;
    }
    if (!window.confirm("¿Cancelar esta programación? El Plan Anual, la capacitación y la matriz no se eliminan.")) {
      return;
    }
    const respuesta = await apiPost<ItemCronograma>(`/api/cronograma/${item.plan_detalle_id}/cancelar`, {});
    if (respuesta.cancelada) {
      return;
    }
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible guardar la programación.");
      return;
    }
    setMensaje(respuesta.message || "La programación fue cancelada.");
    setError(null);
    recargar();
  }

  async function confirmarInicio() {
    if (!iniciarDe) {
      return;
    }
    setGuardando(true);
    const respuesta = iniciarDe.plan_detalle_id
      ? await apiPost<ItemCronograma>(`/api/cronograma/${iniciarDe.plan_detalle_id}/iniciar`, {})
      : await apiPost<ItemCronograma>("/api/cronograma/grupo/iniciar", {
          capacitacion_id: iniciarDe.capacitacion_id,
          anio: iniciarDe.anio,
          mes: iniciarDe.mes,
        });
    setGuardando(false);
    if (respuesta.cancelada) {
      return;
    }
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible iniciar la capacitación.");
      return;
    }
    setIniciarDe(null);
    setMensaje(respuesta.message || "Capacitación iniciada correctamente.");
    setError(null);
    recargar();
    const sid = sesionActiva(respuesta.data);
    if (sid) {
      setPanel({ item: respuesta.data, sesionId: sid });
    }
  }

  const programada = (item: ItemCronograma) => item.estado_operativo === "PROGRAMADA";
  const enEjecucion = (item: ItemCronograma) => item.estado_operativo === "EN_EJECUCION";

  return (
    <>
      <PageHeader
        titulo="Tablero de Cronograma"
        descripcion="Programación operativa según plazos de Asignaciones (y vínculo opcional al plan aprobado). El Plan Anual define qué capacitaciones se contemplan en el año."
      />

      <FiltroCronograma
        valor={filtro}
        procesos={procesos}
        proyectos={proyectos}
        onChange={setFiltro}
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      {cargando && !tablero ? (
        <p className="mb-4 text-sm text-slate-500">Cargando cronograma…</p>
      ) : null}

      {tablero ? (
        <>
          <p className="mb-4 text-sm text-slate-500">
            <span className="font-medium text-hseq-900">{tablero.periodo.etiqueta}</span>
            {tablero.proceso_nombre ? ` · ${tablero.proceso_nombre}` : " · Todos los procesos"}
            {tablero.proyecto ? ` · ${tablero.proyecto}` : ""}
            {" · "}
            {tablero.total === 0
              ? "0 programaciones"
              : `${tablero.total} programación${tablero.total === 1 ? "" : "es"}`}
            {tablero.estado_plan === "APROBADO" ? " · Plan aprobado" : ""}
            {cargando ? " · Actualizando…" : null}
          </p>

          <Table
            columnas={[
              { clave: "desde", etiqueta: "Desde" },
              { clave: "hasta", etiqueta: "Hasta" },
              { clave: "codigo", etiqueta: "Código" },
              { clave: "cap", etiqueta: "Capacitación" },
              { clave: "proceso", etiqueta: "Proceso" },
              { clave: "proyecto", etiqueta: "Proyecto" },
              { clave: "cargo", etiqueta: "Cargo" },
              { clave: "trab", etiqueta: "Trabajadores" },
              { clave: "oportunidad", etiqueta: "Oportunidad" },
              { clave: "estado", etiqueta: "Estado" },
              { clave: "acc", etiqueta: "Acciones" },
            ]}
            vacio="No hay capacitaciones programadas para este período."
            filas={items.map((item) => [
              formatearFecha(item.fecha_desde ?? item.fecha_programada),
              formatearFecha(item.fecha_hasta ?? item.fecha_programada),
              item.codigo,
              item.tema,
              item.proceso_nombre ?? "—",
              item.proyecto ?? "—",
              item.cargos_aplicables.length
                ? item.cargos_aplicables.map((c) => c.nombre_cargo).join(", ")
                : "—",
              `${item.cantidad_programada} trabajador${item.cantidad_programada === 1 ? "" : "es"}`,
              <span key={`o-${item.capacitacion_id}-${item.mes}`} className="flex flex-col gap-0.5 text-xs">
                {(item.ejecutadas_fuera_de_tiempo ?? 0) > 0 ? (
                  <span className="font-medium text-amber-700">
                    {item.ejecutadas_fuera_de_tiempo} fuera de tiempo
                  </span>
                ) : null}
                {(item.pendientes_fuera_plazo ?? 0) > 0 ? (
                  <span className="font-medium text-rose-700">
                    {item.pendientes_fuera_plazo} pendiente(s) fuera de plazo
                  </span>
                ) : null}
                {(item.ejecutadas_fuera_de_tiempo ?? 0) < 1 && (item.pendientes_fuera_plazo ?? 0) < 1
                  ? "—"
                  : null}
              </span>,
              <Badge key={`e-${item.capacitacion_id}-${item.mes}`} tono={tonoEstado(item.estado_operativo)}>
                {etiquetaEstado(item.estado_operativo)}
              </Badge>,
              <span key={`a-${item.capacitacion_id}-${item.mes}`} className="flex flex-wrap gap-1">
                <Button type="button" variante="ghost" onClick={() => setDetalle(item)}>
                  <Eye className="h-4 w-4" aria-hidden />
                </Button>
                <Button type="button" variante="ghost" onClick={() => void abrirTrabajadores(item)}>
                  <Users className="h-4 w-4" aria-hidden />
                </Button>
                {programada(item) && puede("planes.editar") && item.plan_detalle_id ? (
                  <Button type="button" variante="ghost" onClick={() => void cancelarProgramacion(item)}>
                    <Ban className="h-4 w-4" aria-hidden />
                  </Button>
                ) : null}
                {programada(item) && puede("sesiones.crear") ? (
                  <Button type="button" variante="ghost" onClick={() => setIniciarDe(item)}>
                    <Play className="h-4 w-4" aria-hidden />
                    Iniciar
                  </Button>
                ) : null}
                {enEjecucion(item) ? (
                  <Button
                    type="button"
                    variante="ghost"
                    onClick={() => {
                      const sid = sesionActiva(item);
                      if (sid) setPanel({ item, sesionId: sid });
                    }}
                  >
                    <ClipboardCheck className="h-4 w-4" aria-hidden />
                    Ejecutar
                  </Button>
                ) : null}
              </span>,
            ])}
          />
        </>
      ) : null}

      <Modal abierto={detalle !== null} titulo="Detalle de la programación" onCerrar={() => setDetalle(null)}>
        {detalle ? (
          <div className="space-y-4">
            <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase text-slate-500">Capacitación</dt>
                <dd>
                  {detalle.codigo} — {detalle.tema}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Estado</dt>
                <dd>
                  <Badge tono={tonoEstado(detalle.estado_operativo)}>
                    {etiquetaEstado(detalle.estado_operativo)}
                  </Badge>
                </dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-xs uppercase text-slate-500">Objetivo</dt>
                <dd>{detalle.objetivo || "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Duración</dt>
                <dd>{detalle.horas != null ? `${detalle.horas} h` : "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Modalidad</dt>
                <dd>{detalle.metodologia ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Vigencia</dt>
                <dd>{etiquetaVigencia(detalle)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Fecha desde</dt>
                <dd>{formatearFecha(detalle.fecha_desde ?? detalle.fecha_programada)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Fecha hasta</dt>
                <dd>{formatearFecha(detalle.fecha_hasta ?? detalle.fecha_programada)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Oportunidad</dt>
                <dd className="text-sm">
                  {(detalle.ejecutadas_fuera_de_tiempo ?? 0) > 0
                    ? `${detalle.ejecutadas_fuera_de_tiempo} ejecutada(s) fuera de tiempo`
                    : "Sin ejecuciones fuera de tiempo"}
                  {(detalle.pendientes_fuera_plazo ?? 0) > 0
                    ? ` · ${detalle.pendientes_fuera_plazo} pendiente(s) fuera de plazo`
                    : ""}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proceso</dt>
                <dd>{detalle.proceso_nombre ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proyecto</dt>
                <dd>{detalle.proyecto ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Cargo</dt>
                <dd>
                  {detalle.cargos_aplicables.length
                    ? detalle.cargos_aplicables.map((c) => c.nombre_cargo).join(", ")
                    : "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Trabajadores programados</dt>
                <dd>
                  {detalle.cantidad_programada} trabajador
                  {detalle.cantidad_programada === 1 ? "" : "es"}
                </dd>
              </div>
            </dl>

            {(detalle.sesiones ?? []).length > 0 ? (
              <div>
                <p className="mb-2 text-sm font-medium text-slate-700">Ejecución</p>
                <ul className="space-y-2">
                  {detalle.sesiones.map((sesion) => (
                    <li key={sesion.sesion_id} className="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                      <p className="font-medium text-hseq-900">
                        {formatearFecha(sesion.fecha)} {sesion.hora ?? ""}
                      </p>
                      <p className="text-slate-600">
                        {sesion.modalidad_nombre ?? "Sin modalidad"} · {sesion.convocados}/{sesion.cupo_maximo} convocados
                        {sesion.estado === "EJECUTADA" ? " · Finalizada" : ""}
                      </p>
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <p className="text-sm text-slate-500">Aún no se ha iniciado esta capacitación.</p>
            )}
          </div>
        ) : null}
      </Modal>

      <Modal
        abierto={trabajadoresDe !== null}
        titulo="Trabajadores programados"
        onCerrar={() => setTrabajadoresDe(null)}
      >
        {trabajadoresDe ? (
          <div className="space-y-3">
            <p className="text-sm text-slate-600">
              {trabajadoresDe.codigo} — {trabajadoresDe.tema}. Periodo{" "}
              {formatearFecha(trabajadoresDe.fecha_desde)} → {formatearFecha(trabajadoresDe.fecha_hasta)}.{" "}
              {trabajadores.length} trabajador{trabajadores.length === 1 ? "" : "es"} asignados.
              Si falta alguien, puede agregarlo desde la ejecución (crea la asignación) o en Asignaciones.
            </p>
            <Table
              columnas={[
                { clave: "doc", etiqueta: "Documento" },
                { clave: "nom", etiqueta: "Nombre" },
                { clave: "car", etiqueta: "Cargo" },
                { clave: "proy", etiqueta: "Proyecto" },
                { clave: "est", etiqueta: "Estado" },
                { clave: "opc", etiqueta: "Oportunidad" },
              ]}
              vacio="Nadie tiene esta capacitación asignada en el periodo. Cree la asignación en Asignaciones o agréguela al iniciar la ejecución."
              filas={trabajadores.map((t) => [
                t.numero_documento,
                t.persona_nombre,
                t.nombre_cargo ?? "—",
                t.proyecto ?? "—",
                ETIQUETAS_ASIGNACION[t.estado_asignacion] ?? t.estado_asignacion,
                t.fecha_realizacion
                  ? t.ejecutada_fuera_de_tiempo
                    ? "Fuera de tiempo"
                    : "Dentro del tiempo"
                  : t.estado_asignacion === "PENDIENTE_VENCIDA"
                    ? "Pendiente fuera de plazo"
                    : "—",
              ])}
            />
          </div>
        ) : null}
      </Modal>

      <Modal
        abierto={iniciarDe !== null}
        titulo="Iniciar capacitación"
        amplio={iniciarDe !== null && requiereFormularioInicio(iniciarDe)}
        onCerrar={() => setIniciarDe(null)}
      >
        {iniciarDe ? (
          requiereFormularioInicio(iniciarDe) ? (
            <FormularioSesion
              key={`iniciar-${iniciarDe.capacitacion_id}-${iniciarDe.mes}`}
              item={iniciarDe}
              onCancelar={() => setIniciarDe(null)}
              onGuardado={(detalle) => {
                const item = iniciarDe;
                setIniciarDe(null);
                setMensaje("Sesión creada. Puede continuar con la ejecución.");
                setError(null);
                recargar();
                if (detalle?.sesion_id) {
                  setPanel({ item, sesionId: detalle.sesion_id });
                }
              }}
            />
          ) : (
            <div className="space-y-4">
              <p className="text-sm text-slate-600">
                Se iniciará {iniciarDe.codigo} — {iniciarDe.tema} con fecha de sesión{" "}
                {formatearFecha(iniciarDe.fecha_hasta ?? iniciarDe.fecha_programada)} a las 08:00,
                convocando a las personas asignadas en el periodo{" "}
                {formatearFecha(iniciarDe.fecha_desde)} → {formatearFecha(iniciarDe.fecha_hasta)}.
              </p>
              <div className="flex justify-end gap-2">
                <Button type="button" variante="secondary" onClick={() => setIniciarDe(null)}>
                  Cancelar
                </Button>
                <Button type="button" onClick={() => void confirmarInicio()} disabled={guardando}>
                  {guardando ? "Iniciando…" : "Iniciar capacitación"}
                </Button>
              </div>
            </div>
          )
        ) : null}
      </Modal>

      <Modal
        abierto={panel !== null}
        titulo="Ejecución de la capacitación"
        amplio
        onCerrar={() => {
          setPanel(null);
          recargar();
        }}
      >
        {panel ? (
          <PanelOperativo
            item={panel.item}
            sesionId={panel.sesionId}
            onMensaje={(texto) => {
              setMensaje(texto);
              setError(null);
            }}
            onCerrado={() => {
              setPanel(null);
              recargar();
            }}
          />
        ) : null}
      </Modal>
    </>
  );
}
