"use client";

import { useEffect, useState } from "react";
import { FiltroPeriodo, type FiltroPeriodoValor } from "@/components/dashboard/filtro-periodo";
import {
  GraficaCumplimiento,
  GraficaEficacia,
  GraficaHoras,
} from "@/components/dashboard/grafica-cumplimiento";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { Table } from "@/components/ui/table";
import { apiGet, withQuery } from "@/lib/api";
import type { AlertaVencimiento, ResumenDashboard } from "@/lib/tipos";

const ETIQUETAS_ESTADO: Record<string, string> = {
  PENDIENTE: "Pendiente",
  PENDIENTE_PROXIMA_A_VENCER: "Pendiente próxima a vencer",
  PENDIENTE_VENCIDA: "Pendiente vencida",
  COMPLETADA: "Completada",
  PROXIMA_A_VENCER: "Vigencia próxima a vencer",
  VENCIDA: "Vigencia vencida",
};

function tonoEstado(estado: string) {
  if (estado.includes("VENCIDA") || estado === "VENCIDA") return "alto" as const;
  if (estado.includes("PROXIMA")) return "aviso" as const;
  if (estado === "COMPLETADA") return "ok" as const;
  return "neutral" as const;
}

function filtroInicial(): FiltroPeriodoValor {
  const hoy = new Date();
  return {
    tipo: "mensual",
    anio: hoy.getFullYear(),
    mes: hoy.getMonth() + 1,
    trimestre: Math.ceil((hoy.getMonth() + 1) / 3),
  };
}

export default function DashboardPage() {
  return (
    <RequierePermiso permiso="dashboard.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [filtro, setFiltro] = useState<FiltroPeriodoValor>(filtroInicial);
  const [resumen, setResumen] = useState<ResumenDashboard | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const abortado = { actual: false };

    void (async () => {
      setCargando(true);
      setError(null);

      const params: Record<string, string | number> = {
        tipo: filtro.tipo,
        anio: filtro.anio,
      };
      if (filtro.tipo === "mensual") {
        params.mes = filtro.mes;
      }
      if (filtro.tipo === "trimestral") {
        params.trimestre = filtro.trimestre;
      }

      const dash = await apiGet<ResumenDashboard>(withQuery("/api/dashboard", params));

      if (abortado.actual) {
        return;
      }

      if (!dash.success || !dash.data) {
        setError(dash.message || "No fue posible cargar los indicadores.");
        setResumen(null);
        setCargando(false);
        return;
      }

      setResumen(dash.data);
      setCargando(false);
    })();

    return () => {
      abortado.actual = true;
    };
  }, [filtro]);

  return (
    <>
      <PageHeader
        titulo="Indicadores HSEQ"
        descripcion="Cumplimiento del plan anual frente a lo ejecutado. Administración y proyectos se consolidan en un solo valor."
      />

      <FiltroPeriodo valor={filtro} onChange={setFiltro} />

      {error ? <Alert tono="error">{error}</Alert> : null}

      {cargando && !resumen ? (
        <p className="mb-4 text-sm text-slate-500">Cargando indicadores…</p>
      ) : null}

      {resumen ? (
        <>
          <p className="mb-4 text-sm text-slate-500">
            Período: <span className="font-medium text-hseq-900">{resumen.periodo.etiqueta}</span>
            {cargando ? " · Actualizando…" : null}
          </p>

          <div className="mb-6 grid gap-4 sm:grid-cols-2">
            <Card>
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Trabajadores activos</p>
              <p className="mt-1 text-3xl font-semibold text-hseq-900">{resumen.poblacion?.activos ?? 0}</p>
              <p className="mt-1 text-xs text-slate-500">Población laboral vigente. Los inactivos no entran en el cumplimiento actual.</p>
            </Card>
            <Card>
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Trabajadores inactivos</p>
              <p className="mt-1 text-3xl font-semibold text-slate-700">{resumen.poblacion?.inactivos ?? 0}</p>
              <p className="mt-1 text-xs text-slate-500">Conservan historial y se consultan desde el reporte del trabajador.</p>
            </Card>
          </div>

          <div className="mb-6 grid gap-4 lg:grid-cols-3">
            <GraficaCumplimiento
              titulo="Cumplimiento general"
              descripcion="Programado del plan anual vs cumplimientos del período."
              kpi={resumen.cumplimiento_general}
            />
            <GraficaCumplimiento
              titulo="Inducción y reinducción"
              descripcion="Plan filtrado por tipo de inducción; ejecutado por origen o el mismo tipo."
              kpi={resumen.cumplimiento_induccion}
            />
            <GraficaCumplimiento
              titulo="Tareas críticas"
              descripcion="Solo capacitaciones marcadas como tarea crítica."
              kpi={resumen.cumplimiento_tareas_criticas}
            />
          </div>

          <div className="mb-6 grid gap-4 lg:grid-cols-2">
            <GraficaEficacia temas={resumen.eficacia_por_tema} />
            <GraficaHoras temas={resumen.horas_por_tema} />
          </div>

          <Card>
            <h2 className="mb-1 text-sm font-semibold text-hseq-900">Alertas de vencimiento</h2>
            <p className="mb-3 text-xs text-slate-500">
              {resumen.alertas_activas} alerta{resumen.alertas_activas === 1 ? "" : "s"} activa
              {resumen.alertas_activas === 1 ? "" : "s"} · {resumen.pendientes} pendiente
              {resumen.pendientes === 1 ? "" : "s"}
            </p>
            <Table
              columnas={[
                { clave: "estado", etiqueta: "Estado" },
                { clave: "tipo", etiqueta: "Tipo" },
                { clave: "fecha", etiqueta: "Fecha alerta" },
                { clave: "persona", etiqueta: "Persona" },
                { clave: "cap", etiqueta: "Capacitación" },
              ]}
              filas={(resumen.alertas ?? []).map((alerta: AlertaVencimiento) => [
                <Badge key="e" tono={tonoEstado(alerta.estado_calculado)}>
                  {ETIQUETAS_ESTADO[alerta.estado_calculado] ?? alerta.estado_calculado}
                </Badge>,
                alerta.tipo_alerta === "LIMITE_CUMPLIMIENTO" ? "Límite de cumplimiento" : "Vigencia",
                alerta.fecha_alerta ?? "—",
                String(alerta.persona_id_ext),
                String(alerta.capacitacion_id),
              ])}
              vacio="No hay alertas de vencimiento en este momento."
            />
          </Card>
        </>
      ) : null}
    </>
  );
}
