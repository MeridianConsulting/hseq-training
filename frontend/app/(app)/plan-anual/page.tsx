"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { ArrowLeft, Check, Eye, Pencil, Plus, RotateCcw, Send, Trash2 } from "lucide-react";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type {
  CapacitacionPlanOpcion,
  CargoCorporativo,
  DetallePlanAnual,
  OpcionesPlanAnual,
  PlanAnual,
} from "@/lib/tipos";

const MESES = [
  "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
  "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre",
];

function etiquetaEstado(estado: string): string {
  if (estado === "BORRADOR") return "Borrador";
  if (estado === "EN_REVISION") return "Pendiente de aprobación";
  if (estado === "APROBADO") return "Aprobado";
  return estado;
}

function tonoEstado(estado: string) {
  if (estado === "APROBADO") return "ok" as const;
  if (estado === "EN_REVISION") return "aviso" as const;
  return "neutral" as const;
}

function procesoPermiteProyecto(procesoId: string, procesos: OpcionesPlanAnual["procesos"]): boolean {
  const seleccionado = procesos.find((p) => String(p.proceso_id) === procesoId);
  if (!seleccionado) return false;
  const n = seleccionado.nombre
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
  return n.includes("gestion de proyectos");
}

function formatearFecha(iso: string | null): string {
  if (!iso) return "—";
  const partes = iso.slice(0, 10).split("-");
  if (partes.length !== 3) return iso;
  return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function etiquetaCapacitacion(c: CapacitacionPlanOpcion): string {
  return `${c.codigo} — ${c.nombre}`;
}

type FormularioActividad = {
  capacitacion_id: string;
  proceso_id: string;
  proyecto: string;
  fecha_programada: string;
};

const FORM_VACIO: FormularioActividad = {
  capacitacion_id: "",
  proceso_id: "",
  proyecto: "",
  fecha_programada: "",
};

export default function PlanAnualPage() {
  return (
    <RequierePermiso permiso="planes.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [opciones, setOpciones] = useState<OpcionesPlanAnual>({
    procesos: [],
    proyectos: [],
    capacitaciones: [],
  });
  const [items, setItems] = useState<PlanAnual[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [anioFiltro, setAnioFiltro] = useState("");
  const [estadoFiltro, setEstadoFiltro] = useState("");
  const [buscar, setBuscar] = useState("");
  const [plan, setPlan] = useState<PlanAnual | null>(null);
  const [filtroProceso, setFiltroProceso] = useState("");
  const [filtroProyecto, setFiltroProyecto] = useState("");
  const [buscarDetalle, setBuscarDetalle] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [crearAbierto, setCrearAbierto] = useState(false);
  const [anioNuevo, setAnioNuevo] = useState(String(new Date().getFullYear() + 1));
  const [formAbierto, setFormAbierto] = useState(false);
  const [editandoId, setEditandoId] = useState<number | null>(null);
  const [form, setForm] = useState<FormularioActividad>(FORM_VACIO);
  const [alcance, setAlcance] = useState<CargoCorporativo[]>([]);
  const [detalleVer, setDetalleVer] = useState<DetallePlanAnual | null>(null);
  const [guardando, setGuardando] = useState(false);
  const [buscarCap, setBuscarCap] = useState("");

  const muestraProyectoForm = procesoPermiteProyecto(form.proceso_id, opciones.procesos);
  const muestraProyectoFiltro = procesoPermiteProyecto(filtroProceso, opciones.procesos);

  const capsSugeridas = useMemo(() => {
    const q = buscarCap.trim().toLowerCase();
    if (q === "" || form.capacitacion_id !== "") return [];
    return opciones.capacitaciones
      .filter((c) => c.codigo.toLowerCase().includes(q) || c.nombre.toLowerCase().includes(q))
      .slice(0, 12);
  }, [buscarCap, opciones.capacitaciones, form.capacitacion_id]);

  const capSeleccionada = opciones.capacitaciones.find(
    (c) => String(c.capacitacion_id) === form.capacitacion_id,
  );

  useEffect(() => {
    void (async () => {
      const respuesta = await apiGet<OpcionesPlanAnual>("/api/planes-anuales/opciones");
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar las opciones del plan.");
        return;
      }
      setOpciones(respuesta.data);
    })();
  }, []);

  async function cargarListado(paginaActual = 1) {
    const respuesta = await apiGet<ListaPaginada<PlanAnual>>(
      withQuery("/api/planes-anuales", {
        page: paginaActual,
        per_page: 15,
        anio: anioFiltro || undefined,
        estado: estadoFiltro || undefined,
        buscar: buscar.trim() || undefined,
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

  async function abrirPlan(id: number) {
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
  }, []);

  useEffect(() => {
    if (!formAbierto || form.capacitacion_id === "" || form.proceso_id === "") {
      setAlcance([]);
      return;
    }
    if (muestraProyectoForm && form.proyecto === "") {
      setAlcance([]);
      return;
    }
    const abortado = { actual: false };
    void (async () => {
      const respuesta = await apiGet<{ cargos_aplicables: CargoCorporativo[] }>(
        withQuery("/api/planes-anuales/alcance", {
          capacitacion_id: form.capacitacion_id,
          proceso_id: form.proceso_id,
          proyecto: muestraProyectoForm ? form.proyecto : undefined,
        }),
      );
      if (abortado.actual) return;
      if (!respuesta.success || !respuesta.data) {
        setAlcance([]);
        return;
      }
      setAlcance(respuesta.data.cargos_aplicables ?? []);
    })();
    return () => {
      abortado.actual = true;
    };
  }, [formAbierto, form.capacitacion_id, form.proceso_id, form.proyecto, muestraProyectoForm]);

  const actividadesFiltradas = useMemo(() => {
    if (!plan?.detalles) return [];
    const q = buscarDetalle.trim().toLowerCase();
    return plan.detalles.filter((d) => {
      if (filtroProceso !== "" && String(d.proceso_id ?? "") !== filtroProceso) return false;
      if (muestraProyectoFiltro && filtroProyecto !== "" && (d.proyecto ?? "") !== filtroProyecto) {
        return false;
      }
      if (q === "") return true;
      return (
        d.capacitacion_codigo.toLowerCase().includes(q)
        || d.capacitacion_nombre.toLowerCase().includes(q)
      );
    });
  }, [plan, filtroProceso, filtroProyecto, buscarDetalle, muestraProyectoFiltro]);

  const porMes = useMemo(() => {
    const grupos: { mes: number; nombre: string; items: DetallePlanAnual[] }[] = [];
    for (let m = 1; m <= 12; m++) {
      const itemsMes = actividadesFiltradas.filter((d) => d.mes_programado === m);
      if (itemsMes.length > 0) {
        grupos.push({ mes: m, nombre: MESES[m - 1], items: itemsMes });
      }
    }
    return grupos;
  }, [actividadesFiltradas]);

  const chips: ChipFiltro[] = [];
  if (anioFiltro) chips.push({ clave: "anio", etiqueta: "Año", valor: anioFiltro });
  if (estadoFiltro) chips.push({ clave: "estado", etiqueta: "Estado", valor: etiquetaEstado(estadoFiltro) });
  if (buscar.trim()) chips.push({ clave: "buscar", etiqueta: "Capacitación", valor: buscar.trim() });

  function quitarChip(clave: string) {
    if (clave === "anio") setAnioFiltro("");
    if (clave === "estado") setEstadoFiltro("");
    if (clave === "buscar") setBuscar("");
  }

  async function crearPlan(e: FormEvent) {
    e.preventDefault();
    setGuardando(true);
    const respuesta = await apiPost<PlanAnual>("/api/planes-anuales", { anio: Number(anioNuevo) });
    setGuardando(false);
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible guardar el Plan Anual.");
      return;
    }
    setCrearAbierto(false);
    setMensaje(respuesta.message || "Plan Anual guardado correctamente.");
    setError(null);
    await cargarListado(1);
    setPlan(respuesta.data);
  }

  function abrirFormulario(detalle?: DetallePlanAnual) {
    if (detalle) {
      setEditandoId(detalle.plan_detalle_id);
      setForm({
        capacitacion_id: String(detalle.capacitacion_id),
        proceso_id: detalle.proceso_id ? String(detalle.proceso_id) : "",
        proyecto: detalle.proyecto ?? "",
        fecha_programada: detalle.fecha_programada ?? "",
      });
      setBuscarCap(`${detalle.capacitacion_codigo} — ${detalle.capacitacion_nombre}`);
    } else {
      setEditandoId(null);
      setForm({
        ...FORM_VACIO,
        fecha_programada: plan ? `${plan.anio}-01-15` : "",
      });
      setBuscarCap("");
    }
    setFormAbierto(true);
  }

  async function guardarActividad(e: FormEvent) {
    e.preventDefault();
    if (!plan) return;
    setGuardando(true);
    const payload = {
      capacitacion_id: Number(form.capacitacion_id),
      proceso_id: Number(form.proceso_id),
      proyecto: muestraProyectoForm ? form.proyecto : null,
      fecha_programada: form.fecha_programada,
    };
    const respuesta = editandoId
      ? await apiPut<PlanAnual>(`/api/planes-anuales/${plan.plan_anual_id}/actividades/${editandoId}`, payload)
      : await apiPost<PlanAnual>(`/api/planes-anuales/${plan.plan_anual_id}/actividades`, payload);
    setGuardando(false);
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible guardar el Plan Anual.");
      return;
    }
    setPlan(respuesta.data);
    setFormAbierto(false);
    setMensaje(respuesta.message || "Actividad agregada correctamente al Plan Anual.");
    setError(null);
  }

  async function eliminarActividad(detalleId: number) {
    if (!plan) return;
    if (!window.confirm("¿Retirar esta actividad del Plan Anual? La capacitación del catálogo no se elimina.")) {
      return;
    }
    const respuesta = await apiDelete<PlanAnual>(
      `/api/planes-anuales/${plan.plan_anual_id}/actividades/${detalleId}`,
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible retirar la actividad.");
      return;
    }
    setPlan(respuesta.data);
    setMensaje(respuesta.message || "Actividad retirada del Plan Anual.");
    setError(null);
  }

  async function enviarAprobacion() {
    if (!plan) return;
    const respuesta = await apiPost<PlanAnual>(`/api/planes-anuales/${plan.plan_anual_id}/enviar-revision`, {});
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible enviar el Plan Anual a aprobación.");
      return;
    }
    setPlan(respuesta.data);
    setMensaje(respuesta.message || "Plan Anual enviado a aprobación.");
    setError(null);
  }

  async function devolverPlan() {
    if (!plan) return;
    const respuesta = await apiPost<PlanAnual>(`/api/planes-anuales/${plan.plan_anual_id}/devolver`, {});
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible devolver el plan.");
      return;
    }
    setPlan(respuesta.data);
    setMensaje(respuesta.message || "Plan Anual devuelto para corrección.");
    setError(null);
  }

  async function aprobarPlan() {
    if (!plan) return;
    if (!window.confirm("¿Aprobar este Plan Anual? Quedará como el plan aprobado del año.")) {
      return;
    }
    const respuesta = await apiPost<PlanAnual>(`/api/planes-anuales/${plan.plan_anual_id}/aprobar`, {});
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No tiene permisos para aprobar este plan.");
      return;
    }
    setPlan(respuesta.data);
    setMensaje(respuesta.message || "Plan Anual aprobado correctamente.");
    setError(null);
  }

  const editable = plan?.estado === "BORRADOR" && puede("planes.editar");

  if (plan) {
    return (
      <>
        <PageHeader
          titulo={`Plan anual ${plan.anio}`}
          descripcion="Capacitaciones planificadas para el año: dónde aplican (matriz) y cuándo se programan."
          acciones={
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variante="secondary"
                onClick={() => {
                  setPlan(null);
                  void cargarListado(pagina);
                }}
              >
                <ArrowLeft className="h-4 w-4" aria-hidden />
                Volver
              </Button>
              {editable ? (
                <Button type="button" onClick={() => abrirFormulario()}>
                  <Plus className="h-4 w-4" aria-hidden />
                  Agregar actividad
                </Button>
              ) : null}
              {plan.estado === "BORRADOR" && puede("planes.editar") ? (
                <Button type="button" onClick={() => void enviarAprobacion()}>
                  <Send className="h-4 w-4" aria-hidden />
                  Enviar a aprobación
                </Button>
              ) : null}
              {plan.estado === "EN_REVISION" && puede("planes.aprobar") ? (
                <>
                  <Button type="button" variante="secondary" onClick={() => void devolverPlan()}>
                    <RotateCcw className="h-4 w-4" aria-hidden />
                    Devolver
                  </Button>
                  <Button type="button" onClick={() => void aprobarPlan()}>
                    <Check className="h-4 w-4" aria-hidden />
                    Aprobar
                  </Button>
                </>
              ) : null}
            </div>
          }
        />

        {error ? <Alert tono="error">{error}</Alert> : null}
        {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

        {plan.estado === "APROBADO" ? (
          <p className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">
            PLAN ANUAL {plan.anio} · Estado: APROBADO
            <span className="ml-2 font-normal">
              {actividadesFiltradas.length} actividad(es) · {plan.total_horas ?? 0} horas programadas
            </span>
          </p>
        ) : (
          <p className="mb-4 text-sm text-slate-600">
            <Badge tono={tonoEstado(plan.estado)}>{etiquetaEstado(plan.estado)}</Badge>
            <span className="ml-2">
              {actividadesFiltradas.length} actividad(es) · {plan.total_horas ?? 0} horas programadas
            </span>
          </p>
        )}

        <Filters>
          <Field etiqueta="Proceso">
            <select
              className={inputClass}
              value={filtroProceso}
              onChange={(e) => {
                setFiltroProceso(e.target.value);
                if (!procesoPermiteProyecto(e.target.value, opciones.procesos)) {
                  setFiltroProyecto("");
                }
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
          {muestraProyectoFiltro ? (
            <Field etiqueta="Proyecto">
              <select
                className={inputClass}
                value={filtroProyecto}
                onChange={(e) => setFiltroProyecto(e.target.value)}
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
          <Field etiqueta="Buscar">
            <input
              className={inputClass}
              value={buscarDetalle}
              onChange={(e) => setBuscarDetalle(e.target.value)}
              placeholder="Código o nombre"
            />
          </Field>
        </Filters>

        {porMes.length === 0 ? (
          <p className="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
            {plan.detalles?.length
              ? "No hay actividades que coincidan con los filtros."
              : "No hay actividades en este plan. Agregue una capacitación del catálogo."}
          </p>
        ) : (
          <div className="space-y-6">
            {porMes.map((bloque) => (
              <section key={bloque.mes}>
                <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                  {bloque.nombre}
                </h2>
                <Table
                  columnas={[
                    { clave: "fecha", etiqueta: "Fecha" },
                    { clave: "cap", etiqueta: "Capacitación" },
                    { clave: "proceso", etiqueta: "Proceso" },
                    { clave: "proyecto", etiqueta: "Proyecto" },
                    { clave: "cargos", etiqueta: "Cargos / alcance" },
                    { clave: "horas", etiqueta: "Duración" },
                    { clave: "estado", etiqueta: "Estado" },
                    { clave: "acciones", etiqueta: "" },
                  ]}
                  filas={bloque.items.map((d) => [
                    formatearFecha(d.fecha_programada),
                    <span key={`c-${d.plan_detalle_id}`}>
                      <span className="font-medium text-slate-800">
                        {d.capacitacion_codigo} — {d.capacitacion_nombre}
                      </span>
                      {d.es_tarea_critica ? (
                        <span className="ml-2">
                          <Badge tono="alto">Tarea crítica</Badge>
                        </span>
                      ) : null}
                    </span>,
                    d.proceso_nombre ?? "—",
                    d.proyecto ?? "—",
                    d.cargos_aplicables.length
                      ? d.cargos_aplicables.map((c) => c.nombre_cargo).join(", ")
                      : "—",
                    d.duracion_estimada_horas != null ? `${d.duracion_estimada_horas} h` : "—",
                    etiquetaEstado(plan.estado),
                    <span key={`a-${d.plan_detalle_id}`} className="flex flex-wrap gap-1">
                      <Button type="button" variante="ghost" onClick={() => setDetalleVer(d)}>
                        <Eye className="h-4 w-4" aria-hidden />
                      </Button>
                      {editable ? (
                        <>
                          <Button type="button" variante="ghost" onClick={() => abrirFormulario(d)}>
                            <Pencil className="h-4 w-4" aria-hidden />
                          </Button>
                          <Button
                            type="button"
                            variante="ghost"
                            onClick={() => void eliminarActividad(d.plan_detalle_id)}
                          >
                            <Trash2 className="h-4 w-4" aria-hidden />
                          </Button>
                        </>
                      ) : null}
                    </span>,
                  ])}
                />
              </section>
            ))}
          </div>
        )}

        <Modal
          abierto={formAbierto}
          titulo={editandoId ? "Editar actividad" : "Agregar actividad"}
          onCerrar={() => setFormAbierto(false)}
        >
          <form className="space-y-4" onSubmit={(e) => void guardarActividad(e)}>
            <Field etiqueta="Año">
              <input className={inputClass} value={plan.anio} readOnly />
            </Field>
            <Field etiqueta="Fecha programada">
              <input
                className={inputClass}
                type="date"
                required
                min={`${plan.anio}-01-01`}
                max={`${plan.anio}-12-31`}
                value={form.fecha_programada}
                onChange={(e) => setForm((f) => ({ ...f, fecha_programada: e.target.value }))}
              />
            </Field>
            <Field etiqueta="Capacitación">
              <input
                className={inputClass}
                value={buscarCap}
                onChange={(e) => {
                  setBuscarCap(e.target.value);
                  setForm((f) => ({ ...f, capacitacion_id: "" }));
                }}
                placeholder="Escriba código o nombre"
                autoComplete="off"
              />
            </Field>
            {form.capacitacion_id === "" ? (
              <div className="max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50">
                {buscarCap.trim() === "" ? (
                  <p className="px-3 py-3 text-sm text-slate-500">
                    Escriba para ver las capacitaciones que puede programar en el mes.
                  </p>
                ) : capsSugeridas.length === 0 ? (
                  <p className="px-3 py-3 text-sm text-slate-500">No hay capacitaciones que coincidan.</p>
                ) : (
                  capsSugeridas.map((c) => (
                    <button
                      key={c.capacitacion_id}
                      type="button"
                      className="block w-full px-3 py-2 text-left text-sm hover:bg-hseq-50"
                      onClick={() => {
                        setBuscarCap(etiquetaCapacitacion(c));
                        setForm((f) => ({ ...f, capacitacion_id: String(c.capacitacion_id) }));
                      }}
                    >
                      <span className="font-medium text-slate-800">{etiquetaCapacitacion(c)}</span>
                      {c.tipo_nombre ? (
                        <span className="ml-2 text-xs text-slate-500">{c.tipo_nombre}</span>
                      ) : null}
                      {c.duracion_estimada_horas != null ? (
                        <span className="ml-2 text-xs text-slate-500">{c.duracion_estimada_horas} h</span>
                      ) : null}
                    </button>
                  ))
                )}
              </div>
            ) : (
              <p className="text-sm text-hseq-800">
                Seleccionada: {capSeleccionada ? etiquetaCapacitacion(capSeleccionada) : buscarCap}
              </p>
            )}
            <Field etiqueta="Proceso">
              <select
                className={inputClass}
                required
                value={form.proceso_id}
                onChange={(e) => {
                  const valor = e.target.value;
                  setForm((f) => ({
                    ...f,
                    proceso_id: valor,
                    proyecto: procesoPermiteProyecto(valor, opciones.procesos) ? f.proyecto : "",
                  }));
                }}
              >
                <option value="">Seleccione</option>
                {opciones.procesos.map((p) => (
                  <option key={p.proceso_id} value={p.proceso_id}>
                    {p.nombre}
                  </option>
                ))}
              </select>
            </Field>
            {muestraProyectoForm ? (
              <Field etiqueta="Proyecto">
                <select
                  className={inputClass}
                  required
                  value={form.proyecto}
                  onChange={(e) => setForm((f) => ({ ...f, proyecto: e.target.value }))}
                >
                  <option value="">Seleccione</option>
                  {opciones.proyectos.map((nombre) => (
                    <option key={nombre} value={nombre}>
                      {nombre}
                    </option>
                  ))}
                </select>
              </Field>
            ) : null}
            <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
              <p className="mb-1 font-medium">Cargos aplicables (matriz)</p>
              {alcance.length === 0 ? (
                <p className="text-slate-500">
                  Seleccione capacitación y proceso para consultar el alcance. Si no hay marcas en la matriz, no se podrá guardar.
                </p>
              ) : (
                <ul className="list-disc pl-5">
                  {alcance.map((c) => (
                    <li key={c.cargo_id}>{c.nombre_cargo}</li>
                  ))}
                </ul>
              )}
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variante="secondary" onClick={() => setFormAbierto(false)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={guardando || form.capacitacion_id === ""}>
                {guardando ? "Guardando…" : "Guardar"}
              </Button>
            </div>
          </form>
        </Modal>

        <Modal
          abierto={detalleVer !== null}
          titulo="Detalle de la actividad"
          onCerrar={() => setDetalleVer(null)}
        >
          {detalleVer ? (
            <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase text-slate-500">Capacitación</dt>
                <dd>
                  {detalleVer.capacitacion_codigo} — {detalleVer.capacitacion_nombre}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Tipo</dt>
                <dd>{detalleVer.tipo_nombre ?? "—"}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-xs uppercase text-slate-500">Objetivo</dt>
                <dd>{detalleVer.capacitacion_objetivo ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Duración</dt>
                <dd>
                  {detalleVer.duracion_estimada_horas != null
                    ? `${detalleVer.duracion_estimada_horas} horas`
                    : "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Modalidad</dt>
                <dd>{detalleVer.modalidad_nombre ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Vigencia</dt>
                <dd>{detalleVer.vigencia_nombre ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Tarea crítica</dt>
                <dd>{detalleVer.es_tarea_critica ? "Sí" : "No"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proceso</dt>
                <dd>{detalleVer.proceso_nombre ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proyecto</dt>
                <dd>{detalleVer.proyecto ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Fecha programada</dt>
                <dd>{formatearFecha(detalleVer.fecha_programada)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Estado del plan</dt>
                <dd>{etiquetaEstado(plan.estado)}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-xs uppercase text-slate-500">Cargos aplicables</dt>
                <dd>
                  {detalleVer.cargos_aplicables.length
                    ? detalleVer.cargos_aplicables.map((c) => c.nombre_cargo).join(", ")
                    : "—"}
                </dd>
              </div>
            </dl>
          ) : null}
        </Modal>
      </>
    );
  }

  return (
    <>
      <PageHeader
        titulo="Plan anual"
        descripcion="Programe las capacitaciones del año según la matriz de aplicabilidad. Las personas se asignan en el módulo de asignaciones."
        acciones={
          puede("planes.crear") ? (
            <Button type="button" onClick={() => setCrearAbierto(true)}>
              <Plus className="h-4 w-4" aria-hidden />
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
            type="number"
            min={2000}
            max={2100}
            value={anioFiltro}
            onChange={(e) => setAnioFiltro(e.target.value)}
            placeholder="Todos"
          />
        </Field>
        <Field etiqueta="Estado">
          <select className={inputClass} value={estadoFiltro} onChange={(e) => setEstadoFiltro(e.target.value)}>
            <option value="">Todos</option>
            <option value="BORRADOR">Borrador</option>
            <option value="EN_REVISION">Pendiente de aprobación</option>
            <option value="APROBADO">Aprobado</option>
          </select>
        </Field>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={buscar}
            onChange={(e) => setBuscar(e.target.value)}
            placeholder="Código o nombre"
          />
        </Field>
        <div className="flex items-end">
          <Button type="button" variante="secondary" onClick={() => void cargarListado(1)}>
            Filtrar
          </Button>
        </div>
      </Filters>

      <FiltrosActivos
        chips={chips}
        onQuitar={(clave) => {
          quitarChip(clave);
        }}
        onLimpiar={() => {
          setAnioFiltro("");
          setEstadoFiltro("");
          setBuscar("");
        }}
      />

      <Table
        columnas={[
          { clave: "anio", etiqueta: "Año" },
          { clave: "estado", etiqueta: "Estado" },
          { clave: "total", etiqueta: "Programadas" },
          { clave: "aprobacion", etiqueta: "Aprobación" },
          { clave: "acciones", etiqueta: "" },
        ]}
        vacio="No hay planes anuales para los filtros seleccionados."
        filas={items.map((p) => [
          String(p.anio),
          <Badge key={`e-${p.plan_anual_id}`} tono={tonoEstado(p.estado)}>
            {etiquetaEstado(p.estado)}
          </Badge>,
          String(p.total_programadas),
          p.fecha_aprobacion ? formatearFecha(p.fecha_aprobacion.slice(0, 10)) : "—",
          <Button key={`a-${p.plan_anual_id}`} type="button" variante="secondary" onClick={() => void abrirPlan(p.plan_anual_id)}>
            Abrir
          </Button>,
        ])}
      />

      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargarListado(p)} />

      <Modal abierto={crearAbierto} titulo="Crear plan anual" onCerrar={() => setCrearAbierto(false)}>
        <form className="space-y-4" onSubmit={(e) => void crearPlan(e)}>
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
            <Button type="submit" disabled={guardando}>
              {guardando ? "Guardando…" : "Guardar"}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}
