"use client";

import { FormEvent, useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type { AsignacionEnPlan, DetallePlanAnual, PlanAnual } from "@/lib/tipos";

const MESES = [
  "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
  "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre",
];

function etiquetaEstado(estado: string): string {
  if (estado === "BORRADOR") return "Borrador";
  if (estado === "EN_REVISION") return "En revisión";
  if (estado === "APROBADO") return "Aprobado";
  return estado;
}

function tonoEstado(estado: string) {
  if (estado === "APROBADO") return "ok" as const;
  if (estado === "EN_REVISION") return "aviso" as const;
  return "neutral" as const;
}

function origenEtiqueta(origen: string): string {
  if (origen === "AUTOMATICA") return "Automática";
  if (origen === "MANUAL") return "Manual";
  if (origen === "INDUCCION") return "Inducción";
  if (origen === "REINDUCCION") return "Reinducción";
  return origen;
}

export default function PlanAnualPage() {
  return (
    <RequierePermiso permiso="planes.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [items, setItems] = useState<PlanAnual[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [anioFiltro, setAnioFiltro] = useState("");
  const [estadoFiltro, setEstadoFiltro] = useState("");
  const [plan, setPlan] = useState<PlanAnual | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [crearAbierto, setCrearAbierto] = useState(false);
  const [anioNuevo, setAnioNuevo] = useState(String(new Date().getFullYear() + 1));
  const [incluirAbierto, setIncluirAbierto] = useState(false);
  const [disponibles, setDisponibles] = useState<AsignacionEnPlan[]>([]);
  const [buscarDisp, setBuscarDisp] = useState("");
  const [seleccionadas, setSeleccionadas] = useState<string[]>([]);
  const [mesIncluir, setMesIncluir] = useState("1");

  async function cargarListado(paginaActual = 1) {
    const respuesta = await apiGet<ListaPaginada<PlanAnual>>(
      withQuery("/api/planes-anuales", {
        page: paginaActual,
        per_page: 15,
        anio: anioFiltro || undefined,
        estado: estadoFiltro || undefined,
      }),
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar los planes.");
      return;
    }
    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  async function cargarPlan(id: number) {
    const respuesta = await apiGet<PlanAnual>(`/api/planes-anuales/${id}`);
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar el plan.");
      return;
    }
    setPlan(respuesta.data);
    setError(null);
  }

  useEffect(() => {
    void cargarListado(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [anioFiltro, estadoFiltro]);

  useEffect(() => {
    if (!incluirAbierto || !plan) {
      return;
    }
    const id = window.setTimeout(() => {
      void (async () => {
        const r = await apiGet<{ items: AsignacionEnPlan[] }>(
          withQuery(`/api/planes-anuales/${plan.plan_anual_id}/asignaciones-disponibles`, {
            buscar: buscarDisp.trim() || undefined,
          }),
        );
        setDisponibles(r.data?.items ?? []);
      })();
    }, 300);
    return () => window.clearTimeout(id);
  }, [incluirAbierto, buscarDisp, plan]);

  async function crear(evento: FormEvent) {
    evento.preventDefault();
    const respuesta = await apiPost<PlanAnual>("/api/planes-anuales", { anio: Number(anioNuevo) });
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible guardar el plan.");
      return;
    }
    setMensaje(respuesta.message);
    setCrearAbierto(false);
    setPlan(respuesta.data);
    await cargarListado(1);
  }

  async function incluir(evento: FormEvent) {
    evento.preventDefault();
    if (!plan) return;
    const n = seleccionadas.length;
    if (
      !confirm(
        `¿Desea incluir ${n} asignación${n === 1 ? "" : "es"} en ${MESES[Number(mesIncluir) - 1]}?`,
      )
    ) {
      return;
    }
    const respuesta = await apiPost(`/api/planes-anuales/${plan.plan_anual_id}/asignaciones`, {
      asignacion_ids: seleccionadas.map(Number),
      mes_programado: Number(mesIncluir),
    });
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible incluir las asignaciones.");
      return;
    }
    setMensaje(respuesta.message);
    setIncluirAbierto(false);
    setSeleccionadas([]);
    await cargarPlan(plan.plan_anual_id);
  }

  async function quitar(asignacionId: number) {
    if (!plan || !confirm("¿Quitar esta asignación del plan?")) return;
    const respuesta = await apiDelete<PlanAnual>(
      `/api/planes-anuales/${plan.plan_anual_id}/asignaciones/${asignacionId}`,
    );
    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo quitar.");
      return;
    }
    setMensaje(respuesta.message);
    await cargarPlan(plan.plan_anual_id);
  }

  async function mover(asignacionId: number, mes: number) {
    if (!plan) return;
    const respuesta = await apiPut<PlanAnual>(
      `/api/planes-anuales/${plan.plan_anual_id}/asignaciones/${asignacionId}`,
      { mes_programado: mes },
    );
    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo cambiar el mes.");
      return;
    }
    setPlan(respuesta.data);
  }

  async function enviarRevision() {
    if (!plan) return;
    const respuesta = await apiPost<PlanAnual>(
      `/api/planes-anuales/${plan.plan_anual_id}/enviar-revision`,
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible enviar a revisión.");
      return;
    }
    setMensaje(respuesta.message);
    setPlan(respuesta.data);
    await cargarListado(pagina);
  }

  async function aprobar() {
    if (!plan || !confirm("¿Aprobar este plan anual? Pasará a contabilizarse como programado.")) {
      return;
    }
    const respuesta = await apiPost<PlanAnual>(`/api/planes-anuales/${plan.plan_anual_id}/aprobar`);
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No tiene permisos para aprobar este plan.");
      return;
    }
    setMensaje(respuesta.message);
    setPlan(respuesta.data);
    await cargarListado(pagina);
  }

  if (plan) {
    const detalles = plan.detalles ?? [];
    const esBorrador = plan.estado === "BORRADOR";

    return (
      <>
        <PageHeader
          titulo={`Plan anual ${plan.anio}`}
          descripcion="Consolida asignaciones automáticas y manuales. Solo el plan aprobado cuenta como programado."
          acciones={
            <span className="flex flex-wrap gap-2">
              <Button type="button" variante="secondary" onClick={() => setPlan(null)}>
                Volver al listado
              </Button>
              {esBorrador && puede("planes.editar") ? (
                <Button type="button" onClick={() => setIncluirAbierto(true)}>
                  Incluir asignaciones
                </Button>
              ) : null}
              {esBorrador && puede("planes.editar") ? (
                <Button type="button" variante="secondary" onClick={() => void enviarRevision()}>
                  Enviar a revisión
                </Button>
              ) : null}
              {plan.estado === "EN_REVISION" && puede("planes.aprobar") ? (
                <Button type="button" onClick={() => void aprobar()}>
                  Aprobar plan
                </Button>
              ) : null}
            </span>
          }
        />
        {error ? <Alert tono="error">{error}</Alert> : null}
        {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}
        <p className="mb-4 text-sm text-slate-600">
          <Badge tono={tonoEstado(plan.estado)}>{etiquetaEstado(plan.estado)}</Badge>
          <span className="ml-2">
            {plan.total_programadas} capacitación
            {plan.total_programadas === 1 ? "" : "es"} en el plan
            {plan.estado !== "APROBADO" ? " (aún no cuentan como programadas)" : ""}
          </span>
        </p>

        {[1, 2, 3, 4].map((trimestre) => {
          const meses = [1, 2, 3].map((i) => (trimestre - 1) * 3 + i);
          const delTrimestre = detalles.filter((d) => d.trimestre === trimestre);
          return (
            <Card key={trimestre} className="mb-4">
              <h2 className="mb-3 text-sm font-semibold text-hseq-900">
                Trimestre {trimestre}
                <span className="ml-2 font-normal text-slate-500">
                  {delTrimestre.reduce((acc, d) => acc + d.cantidad_programada, 0)} programada
                  {delTrimestre.length === 1 ? "" : "s"}
                </span>
              </h2>
              <div className="grid gap-4 md:grid-cols-3">
                {meses.map((mes) => {
                  const delMes = detalles.filter((d) => d.mes_programado === mes);
                  return (
                    <div key={mes}>
                      <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                        {MESES[mes - 1]} · {delMes.reduce((a, d) => a + d.cantidad_programada, 0)}
                      </p>
                      {delMes.length === 0 ? (
                        <p className="text-sm text-slate-400">Sin capacitaciones</p>
                      ) : (
                        delMes.map((d) => (
                          <BloqueDetalle
                            key={d.plan_detalle_id}
                            detalle={d}
                            editable={esBorrador && puede("planes.editar")}
                            onQuitar={(id) => void quitar(id)}
                            onMover={(id, m) => void mover(id, m)}
                          />
                        ))
                      )}
                    </div>
                  );
                })}
              </div>
            </Card>
          );
        })}

        <Modal
          abierto={incluirAbierto}
          titulo="Incluir asignaciones"
          onCerrar={() => setIncluirAbierto(false)}
        >
          <form className="space-y-4" onSubmit={incluir}>
            <Field etiqueta="Mes de programación">
              <select
                className={inputClass}
                value={mesIncluir}
                onChange={(e) => setMesIncluir(e.target.value)}
              >
                {MESES.map((nombre, i) => (
                  <option key={nombre} value={i + 1}>
                    {nombre}
                  </option>
                ))}
              </select>
            </Field>
            <Field etiqueta="Buscar asignación">
              <input
                className={inputClass}
                value={buscarDisp}
                onChange={(e) => setBuscarDisp(e.target.value)}
                placeholder="Documento, nombre o capacitación"
              />
            </Field>
            <div className="max-h-56 overflow-y-auto rounded-lg border border-slate-200">
              {disponibles.length === 0 ? (
                <p className="px-3 py-4 text-sm text-slate-500">No hay asignaciones disponibles.</p>
              ) : (
                disponibles.map((a) => {
                  const id = String(a.asignacion_id);
                  const marcado = seleccionadas.includes(id);
                  return (
                    <label
                      key={a.asignacion_id}
                      className={`flex cursor-pointer items-start gap-2 px-3 py-2 text-sm hover:bg-hseq-50 ${
                        marcado ? "bg-hseq-50" : ""
                      }`}
                    >
                      <input
                        type="checkbox"
                        className="mt-1"
                        checked={marcado}
                        onChange={() =>
                          setSeleccionadas((prev) =>
                            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
                          )
                        }
                      />
                      <span>
                        {a.capacitacion_codigo} — {a.capacitacion_nombre}
                        <span className="mt-0.5 block text-xs text-slate-500">
                          {a.persona_nombre ?? `Persona ${a.persona_id_ext}`} · {a.numero_documento ?? "—"} ·{" "}
                          {origenEtiqueta(a.origen)}
                        </span>
                      </span>
                    </label>
                  );
                })
              )}
            </div>
            <p className="text-xs text-slate-500">{seleccionadas.length} seleccionada(s).</p>
            <div className="flex justify-end gap-2">
              <Button type="button" variante="secondary" onClick={() => setIncluirAbierto(false)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={seleccionadas.length < 1}>
                Incluir en el plan
              </Button>
            </div>
          </form>
        </Modal>
      </>
    );
  }

  return (
    <>
      <PageHeader
        titulo="Plan anual"
        descripcion="Programe las capacitaciones asignadas por año. El indicador de programado solo usa planes aprobados."
        acciones={
          puede("planes.crear") ? (
            <Button type="button" onClick={() => setCrearAbierto(true)}>
              Crear plan
            </Button>
          ) : null
        }
      />
      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}
      <Filters>
        <Field etiqueta="Año">
          <input
            className={inputClass}
            value={anioFiltro}
            onChange={(e) => setAnioFiltro(e.target.value)}
            placeholder="Todos"
            inputMode="numeric"
          />
        </Field>
        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={estadoFiltro}
            onChange={(e) => setEstadoFiltro(e.target.value)}
          >
            <option value="">Todos</option>
            <option value="BORRADOR">Borrador</option>
            <option value="EN_REVISION">En revisión</option>
            <option value="APROBADO">Aprobado</option>
          </select>
        </Field>
      </Filters>
      <Table
        columnas={[
          { clave: "anio", etiqueta: "Año" },
          { clave: "estado", etiqueta: "Estado" },
          { clave: "total", etiqueta: "En el plan" },
          { clave: "aprobacion", etiqueta: "Aprobación" },
          { clave: "acciones", etiqueta: "" },
        ]}
        filas={items.map((item) => [
          String(item.anio),
          <Badge key="e" tono={tonoEstado(item.estado)}>
            {etiquetaEstado(item.estado)}
          </Badge>,
          String(item.total_programadas),
          item.fecha_aprobacion ? String(item.fecha_aprobacion).slice(0, 10) : "—",
          <Button
            key="v"
            type="button"
            variante="ghost"
            onClick={() => void cargarPlan(item.plan_anual_id)}
          >
            Abrir
          </Button>,
        ])}
        vacio="No hay planes anuales para los filtros seleccionados."
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargarListado(p)} />

      <Modal abierto={crearAbierto} titulo="Crear plan anual" onCerrar={() => setCrearAbierto(false)}>
        <form className="space-y-4" onSubmit={crear}>
          <Field etiqueta="Año">
            <input
              className={inputClass}
              type="number"
              min={2000}
              max={2100}
              required
              value={anioNuevo}
              onChange={(e) => setAnioNuevo(e.target.value)}
            />
          </Field>
          <div className="flex justify-end gap-2">
            <Button type="button" variante="secondary" onClick={() => setCrearAbierto(false)}>
              Cancelar
            </Button>
            <Button type="submit">Crear borrador</Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function BloqueDetalle({
  detalle,
  editable,
  onQuitar,
  onMover,
}: {
  detalle: DetallePlanAnual;
  editable: boolean;
  onQuitar: (asignacionId: number) => void;
  onMover: (asignacionId: number, mes: number) => void;
}) {
  return (
    <div className="mb-3 rounded-lg border border-slate-100 p-2 text-sm">
      <p className="font-medium text-hseq-900">{detalle.capacitacion_nombre}</p>
      <p className="text-xs text-slate-500">
        {detalle.capacitacion_codigo} · {detalle.cantidad_programada} persona
        {detalle.cantidad_programada === 1 ? "" : "s"}
      </p>
      {detalle.asignaciones.map((a) => (
        <div key={a.asignacion_id} className="mt-2 border-t border-slate-50 pt-2">
          <p>
            {a.persona_nombre ?? `Persona ${a.persona_id_ext}`}
            <span className="ml-1 text-xs text-slate-500">{origenEtiqueta(a.origen)}</span>
          </p>
          {editable ? (
            <span className="mt-1 flex flex-wrap items-center gap-2">
              <select
                className="rounded border border-slate-200 px-1 py-0.5 text-xs"
                value={detalle.mes_programado}
                onChange={(e) => onMover(a.asignacion_id, Number(e.target.value))}
              >
                {MESES.map((nombre, i) => (
                  <option key={nombre} value={i + 1}>
                    {nombre}
                  </option>
                ))}
              </select>
              <Button type="button" variante="danger" className="px-2 py-1 text-xs" onClick={() => onQuitar(a.asignacion_id)}>
                Quitar
              </Button>
            </span>
          ) : null}
        </div>
      ))}
    </div>
  );
}
