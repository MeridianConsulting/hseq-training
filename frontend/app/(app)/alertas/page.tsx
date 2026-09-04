"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { apiDownload, apiGet, withQuery } from "@/lib/api";
import type {
  AlertaProximaVencer,
  ListaAlertas,
  OpcionesAlertas,
  ResumenAlertas,
  SoporteCumplimiento,
} from "@/lib/tipos";

function formatoFecha(valor: string | null | undefined): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function etiquetaDias(dias: number): string {
  if (dias < 0) {
    const abs = Math.abs(dias);
    if (abs > 30) return "Más de 30 días";
    return abs === 1 ? "Venció hace 1 día" : `Venció hace ${abs} días`;
  }
  if (dias === 0) return "Vence hoy";
  if (dias > 30) return "Más de 30 días";
  return dias === 1 ? "1 día" : `${dias} días`;
}

function badgeEstadoAlerta(estado: string): { tono: "alto" | "aviso" | "neutral"; etiqueta: string } {
  if (estado === "PENDIENTE_VENCIDA" || estado === "VENCIDA") {
    return { tono: "alto", etiqueta: "Vencida" };
  }
  if (estado === "PENDIENTE_PROXIMA_A_VENCER" || estado === "PROXIMA_A_VENCER") {
    return { tono: "aviso", etiqueta: "Próxima a vencer" };
  }
  return { tono: "aviso", etiqueta: estado || "Alerta" };
}

function procesoPermiteProyecto(
  procesoId: string,
  procesos: OpcionesAlertas["procesos"]
): boolean {
  const seleccionado = procesos.find((p) => String(p.proceso_id) === procesoId);
  if (!seleccionado) return false;
  const n = seleccionado.nombre
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
  return n.includes("gestion de proyectos");
}

function rutaHistorial(item: AlertaProximaVencer): string {
  return withQuery("/asignaciones", {
    persona_id: item.persona_id_ext ?? undefined,
    nombre: item.trabajador,
    documento: item.documento,
  });
}

function etiquetaCapacitacion(item: AlertaProximaVencer): string {
  if (item.capacitacion_codigo && item.capacitacion_nombre) {
    return `${item.capacitacion_codigo} — ${item.capacitacion_nombre}`;
  }
  return item.capacitacion_nombre ?? item.capacitacion_codigo ?? "—";
}

export default function Page() {
  return (
    <RequierePermiso permiso="alertas.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [items, setItems] = useState<AlertaProximaVencer[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [resumen, setResumen] = useState<ResumenAlertas>({ vencidas: 0, proximas_30: 0 });
  const [procesoId, setProcesoId] = useState("");
  const [proyecto, setProyecto] = useState("");
  const [estadoAlerta, setEstadoAlerta] = useState("todas");
  const [q, setQ] = useState("");
  const [qAplicado, setQAplicado] = useState("");
  const [capacitacionId, setCapacitacionId] = useState("");
  const [desde, setDesde] = useState("");
  const [hasta, setHasta] = useState("");
  const [cargando, setCargando] = useState(true);
  const [opciones, setOpciones] = useState<OpcionesAlertas>({
    procesos: [],
    proyectos: [],
    cargos: [],
    capacitaciones: [],
  });
  const [error, setError] = useState<string | null>(null);
  const [detalle, setDetalle] = useState<AlertaProximaVencer | null>(null);
  const [soportesDetalle, setSoportesDetalle] = useState<SoporteCumplimiento[]>([]);
  const [cargandoDetalle, setCargandoDetalle] = useState(false);

  const muestraProyecto = procesoPermiteProyecto(procesoId, opciones.procesos);

  async function cargar(paginaActual = 1) {
    setCargando(true);
    const respuesta = await apiGet<ListaAlertas>(
      withQuery("/api/alertas", {
        page: paginaActual,
        per_page: 15,
        proceso_id: procesoId || undefined,
        proyecto: muestraProyecto && proyecto ? proyecto : undefined,
        estado_alerta: estadoAlerta !== "todas" ? estadoAlerta : undefined,
        q: qAplicado || undefined,
        capacitacion_id: capacitacionId || undefined,
        vencimiento_desde: desde || undefined,
        vencimiento_hasta: hasta || undefined,
      }),
    );
    setCargando(false);
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar las alertas. Intente nuevamente.");
      return;
    }
    setItems(respuesta.data.items);
    setTotal(respuesta.data.pagination.total);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setResumen(respuesta.data.resumen ?? { vencidas: 0, proximas_30: 0 });
    setError(null);
  }

  function limpiarFiltros() {
    setProcesoId("");
    setProyecto("");
    setEstadoAlerta("todas");
    setQ("");
    setQAplicado("");
    setCapacitacionId("");
    setDesde("");
    setHasta("");
  }

  async function abrirDetalle(item: AlertaProximaVencer) {
    setDetalle(item);
    setSoportesDetalle([]);
    if (!item.cumplimiento_id) {
      return;
    }
    setCargandoDetalle(true);
    const r = await apiGet<SoporteCumplimiento[]>(
      `/api/cumplimientos/${item.cumplimiento_id}/soportes`,
    );
    setCargandoDetalle(false);
    if (r.success && r.data) {
      setSoportesDetalle(r.data);
    }
  }

  useEffect(() => {
    void (async () => {
      const respuesta = await apiGet<OpcionesAlertas>("/api/alertas/opciones");
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar las alertas. Intente nuevamente.");
        return;
      }
      setOpciones(respuesta.data);
    })();
  }, []);

  useEffect(() => {
    if (!muestraProyecto && proyecto !== "") {
      setProyecto("");
      return;
    }
    void cargar(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [procesoId, proyecto, estadoAlerta, qAplicado, capacitacionId, desde, hasta, muestraProyecto]);

  const chips: ChipFiltro[] = [];
  if (procesoId) {
    const proceso = opciones.procesos.find((p) => String(p.proceso_id) === procesoId);
    chips.push({ clave: "proceso_id", etiqueta: "Proceso", valor: proceso?.nombre ?? procesoId });
  }
  if (muestraProyecto && proyecto) {
    chips.push({ clave: "proyecto", etiqueta: "Proyecto", valor: proyecto });
  }
  if (estadoAlerta !== "todas") {
    chips.push({
      clave: "estado",
      etiqueta: "Estado",
      valor: estadoAlerta === "vencidas" ? "Vencidas" : "Próximas a vencer",
    });
  }
  if (qAplicado) {
    chips.push({ clave: "q", etiqueta: "Empleado", valor: qAplicado });
  }
  if (capacitacionId) {
    const cap = (opciones.capacitaciones ?? []).find(
      (c) => String(c.capacitacion_id) === capacitacionId,
    );
    chips.push({
      clave: "cap",
      etiqueta: "Capacitación",
      valor: cap ? `${cap.codigo} — ${cap.nombre}` : capacitacionId,
    });
  }
  if (desde) chips.push({ clave: "desde", etiqueta: "Desde", valor: formatoFecha(desde) });
  if (hasta) chips.push({ clave: "hasta", etiqueta: "Hasta", valor: formatoFecha(hasta) });

  function quitarChip(clave: string) {
    if (clave === "proceso_id") setProcesoId("");
    if (clave === "proyecto") setProyecto("");
    if (clave === "estado") setEstadoAlerta("todas");
    if (clave === "q") {
      setQ("");
      setQAplicado("");
    }
    if (clave === "cap") setCapacitacionId("");
    if (clave === "desde") setDesde("");
    if (clave === "hasta") setHasta("");
  }

  return (
    <>
      <PageHeader
        titulo="Alertas"
        descripcion="Capacitaciones vencidas o próximas a vencer que requieren atención."
        acciones={
          <div className="flex gap-3">
            <Card className="min-w-[8.5rem] py-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Vencidas</p>
              <p className="mt-1 text-2xl font-semibold text-rose-700">{resumen.vencidas}</p>
            </Card>
            <Card className="min-w-[8.5rem] py-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                ≤ 30 días
              </p>
              <p className="mt-1 text-2xl font-semibold text-amber-700">{resumen.proximas_30}</p>
            </Card>
          </div>
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}

      <Filters>
        <Field etiqueta="Proceso">
          <select
            className={inputClass}
            value={procesoId}
            onChange={(e) => {
              const valor = e.target.value;
              setProcesoId(valor);
              if (!procesoPermiteProyecto(valor, opciones.procesos)) {
                setProyecto("");
              }
            }}
          >
            <option value="">Todos</option>
            {opciones.procesos.map((item) => (
              <option key={item.proceso_id} value={item.proceso_id}>
                {item.nombre}
              </option>
            ))}
          </select>
        </Field>

        {muestraProyecto ? (
          <Field etiqueta="Proyecto">
            <select
              className={inputClass}
              value={proyecto}
              onChange={(e) => setProyecto(e.target.value)}
            >
              <option value="">Todos los proyectos</option>
              {opciones.proyectos.map((nombre) => (
                <option key={nombre} value={nombre}>
                  {nombre}
                </option>
              ))}
            </select>
          </Field>
        ) : null}

        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={estadoAlerta}
            onChange={(e) => setEstadoAlerta(e.target.value)}
          >
            <option value="todas">Todas</option>
            <option value="proximas">Próximas a vencer</option>
            <option value="vencidas">Vencidas</option>
          </select>
        </Field>

        <Field etiqueta="Empleado">
          <input
            className={inputClass}
            value={q}
            placeholder="Nombre o cédula"
            onChange={(e) => setQ(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") setQAplicado(q.trim());
            }}
            onBlur={() => setQAplicado(q.trim())}
          />
        </Field>

        <Field etiqueta="Capacitación">
          <select
            className={inputClass}
            value={capacitacionId}
            onChange={(e) => setCapacitacionId(e.target.value)}
          >
            <option value="">Todas</option>
            {(opciones.capacitaciones ?? []).map((cap) => (
              <option key={cap.capacitacion_id} value={cap.capacitacion_id}>
                {cap.codigo} — {cap.nombre}
              </option>
            ))}
          </select>
        </Field>

        <Field etiqueta="Vencimiento desde">
          <input
            type="date"
            className={inputClass}
            value={desde}
            onChange={(e) => setDesde(e.target.value)}
          />
        </Field>

        <Field etiqueta="Vencimiento hasta">
          <input
            type="date"
            className={inputClass}
            value={hasta}
            onChange={(e) => setHasta(e.target.value)}
          />
        </Field>
      </Filters>

      <FiltrosActivos chips={chips} onQuitar={quitarChip} onLimpiar={limpiarFiltros} />

      <p className="mb-3 text-sm text-slate-500">
        {total} alerta{total === 1 ? "" : "s"}
        {cargando ? " · Actualizando…" : null}
      </p>

      {cargando && items.length === 0 ? (
        <ListaCargando />
      ) : (
        <Table
          columnas={[
            { clave: "trabajador", etiqueta: "Trabajador" },
            { clave: "documento", etiqueta: "Cédula" },
            { clave: "cargo", etiqueta: "Cargo" },
            { clave: "proceso", etiqueta: "Proceso" },
            { clave: "proyecto", etiqueta: "Proyecto" },
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "realizacion", etiqueta: "Última ejecución" },
            { clave: "vence", etiqueta: "Vencimiento" },
            { clave: "dias", etiqueta: "Días restantes" },
            { clave: "estado", etiqueta: "Estado" },
            { clave: "acciones", etiqueta: "" },
          ]}
          filas={items.map((item) => {
            const badge = badgeEstadoAlerta(item.estado);
            const claveFila = item.cumplimiento_id ?? item.asignacion_id;
            return [
              item.trabajador ?? (item.persona_id_ext ? `Persona ${item.persona_id_ext}` : "—"),
              item.documento ?? "—",
              item.cargo ?? "—",
              item.proceso ?? "—",
              item.proyecto ?? "—",
              etiquetaCapacitacion(item),
              formatoFecha(item.fecha_realizacion),
              formatoFecha(item.fecha_vencimiento),
              etiquetaDias(item.dias_restantes),
              <Badge key={`e-${claveFila}`} tono={badge.tono}>
                {badge.etiqueta}
              </Badge>,
              <div key={`a-${claveFila}`} className="flex flex-col gap-1 text-sm">
                <button
                  type="button"
                  className="text-left font-medium text-hseq-800 underline-offset-2 hover:underline"
                  onClick={() => void abrirDetalle(item)}
                >
                  Ver detalle
                </button>
                {item.persona_id_ext ? (
                  <Link
                    href={rutaHistorial(item)}
                    className="font-medium text-slate-600 underline-offset-2 hover:underline"
                  >
                    Ver trabajador
                  </Link>
                ) : null}
              </div>,
            ];
          })}
          vacio="No hay alertas de vencimiento para los filtros seleccionados."
        />
      )}
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />

      <Modal
        abierto={detalle !== null}
        titulo="Detalle de la alerta"
        onCerrar={() => setDetalle(null)}
      >
        {detalle ? (
          <div className="space-y-4 text-sm">
            <dl className="grid gap-3 sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase text-slate-500">Trabajador</dt>
                <dd className="font-medium text-hseq-900">{detalle.trabajador ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Cédula</dt>
                <dd>{detalle.documento ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Cargo</dt>
                <dd>{detalle.cargo ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proceso</dt>
                <dd>{detalle.proceso ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Proyecto</dt>
                <dd>{detalle.proyecto ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Capacitación</dt>
                <dd>{etiquetaCapacitacion(detalle)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Última ejecución</dt>
                <dd>{formatoFecha(detalle.fecha_realizacion)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Vencimiento</dt>
                <dd>{formatoFecha(detalle.fecha_vencimiento)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Días restantes</dt>
                <dd>{etiquetaDias(detalle.dias_restantes)}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Estado</dt>
                <dd>
                  <Badge tono={badgeEstadoAlerta(detalle.estado).tono}>
                    {badgeEstadoAlerta(detalle.estado).etiqueta}
                  </Badge>
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-slate-500">Resultado / evaluación</dt>
                <dd>
                  {detalle.resultado ?? "—"}
                  {detalle.nota_evaluacion != null
                    ? ` · Nota ${detalle.nota_evaluacion}`
                    : ""}
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
                  ) : (
                    "—"
                  )}
                </dd>
              </div>
            </dl>

            {detalle.persona_id_ext ? (
              <Link
                href={rutaHistorial(detalle)}
                className="inline-flex font-medium text-hseq-800 underline-offset-2 hover:underline"
              >
                Ir al historial del trabajador
              </Link>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </>
  );
}
