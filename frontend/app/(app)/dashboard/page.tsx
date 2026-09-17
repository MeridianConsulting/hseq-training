"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  FiltroPeriodo,
  type FiltroDashboardValor,
} from "@/components/dashboard/filtro-periodo";
import {
  GraficaCumplimiento,
  TarjetaEficacia,
  TarjetaHoras,
  TarjetaSoportes,
} from "@/components/dashboard/grafica-cumplimiento";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { apiGet, withQuery } from "@/lib/api";
import type { ResumenDashboard } from "@/lib/tipos";

function filtroInicial(): FiltroDashboardValor {
  const hoy = new Date();
  const mes = hoy.getMonth() + 1;
  return {
    proceso: "todos",
    proyecto: "",
    tipo: "mensual",
    anio: hoy.getFullYear(),
    mes,
    trimestre: Math.ceil(mes / 3),
    semestre: mes <= 6 ? 1 : 2,
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
  const [filtro, setFiltro] = useState<FiltroDashboardValor>(filtroInicial);
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
        proceso: filtro.proceso,
      };
      if (filtro.tipo === "mensual") {
        params.mes = filtro.mes;
      }
      if (filtro.tipo === "trimestral") {
        params.trimestre = filtro.trimestre;
      }
      if (filtro.tipo === "semestral") {
        params.semestre = filtro.semestre;
      }
      if (filtro.proyecto !== "") {
        params.proyecto = filtro.proyecto;
      }

      const dash = await apiGet<ResumenDashboard>(withQuery("/api/dashboard", params));

      if (abortado.actual) {
        return;
      }

      if (dash.cancelada) {
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

  const cobertura = resumen?.cobertura;
  const eficacia = resumen?.eficacia;
  const horas = resumen?.horas;
  const alertas = resumen?.alertas_resumen;

  return (
    <>
      <PageHeader
        titulo="Panel de control"
        descripcion="Vista ejecutiva del programa: Asignaciones definen lo programado, Cronograma registra lo ejecutado, Cumplimientos consolida y el Panel presenta indicadores. Solo consulta."
        acciones={
          resumen ? (
            <Card className="min-w-[11rem] py-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Empleados
              </p>
              <dl className="mt-2 space-y-1 text-sm">
                <div className="flex justify-between gap-6">
                  <dt className="text-slate-600">Activos</dt>
                  <dd className="font-semibold text-hseq-900">{resumen.poblacion.activos}</dd>
                </div>
                <div className="flex justify-between gap-6">
                  <dt className="text-slate-600">Inactivos</dt>
                  <dd className="font-semibold text-slate-700">{resumen.poblacion.inactivos}</dd>
                </div>
              </dl>
            </Card>
          ) : null
        }
      />

      <FiltroPeriodo
        valor={filtro}
        onChange={setFiltro}
        procesos={resumen?.opciones.procesos ?? []}
        proyectos={resumen?.opciones.proyectos ?? []}
      />

      {error ? <Alert tono="error">{error}</Alert> : null}

      {cargando && !resumen ? (
        <p className="mb-4 text-sm text-slate-500">Cargando indicadores…</p>
      ) : null}

      {resumen && cobertura && eficacia && horas ? (
        <>
          <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
              <h1 className="text-lg font-semibold uppercase tracking-wide text-hseq-900">
                Cumplimiento general
              </h1>
              <p className="mt-1 text-sm text-slate-500">
                Período:{" "}
                <span className="font-medium text-hseq-900">{resumen.periodo.etiqueta}</span>
                {cargando ? " · Actualizando…" : null}
              </p>
            </div>
            {alertas ? (
              <Card className="py-3 px-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Alertas (resumen)
                </p>
                <p className="mt-1 text-sm text-slate-700">
                  <span className="font-semibold text-amber-700">{alertas.proximas}</span> próximas
                  {" · "}
                  <span className="font-semibold text-red-700">{alertas.vencidas}</span> vencidas
                </p>
                <Link
                  href="/alertas"
                  prefetch={false}
                  className="mt-1 inline-block text-sm font-medium text-hseq-800 underline-offset-2 hover:underline"
                >
                  Ver alertas
                </Link>
              </Card>
            ) : null}
          </div>

          <section className="mb-8">
            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
              Cobertura
            </h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <GraficaCumplimiento
                titulo="Cobertura general"
                descripcion="Ejecutadas (APROBADO) / programadas (Asignaciones) × 100."
                kpi={cobertura.general}
                href="/reportes?tipo=cumplimiento_general"
              />
              <GraficaCumplimiento
                titulo="Inducción y reinducción"
                descripcion="Solo capacitaciones de inducción/reinducción."
                kpi={cobertura.induccion}
                href="/reportes?tipo=inducciones"
              />
              <GraficaCumplimiento
                titulo="Tareas críticas"
                descripcion="Solo capacitaciones marcadas como tarea crítica."
                kpi={cobertura.tareas_criticas}
                href="/reportes?tipo=tareas_criticas"
              />
            </div>
            <Card className="mt-4 max-w-md py-4">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Control de oportunidad
              </p>
              <p className="mt-1 text-sm font-medium text-slate-700">Ejecutadas fuera de tiempo</p>
              <p className="mt-1 text-3xl font-semibold text-hseq-900">
                {resumen.ejecutadas_fuera_de_tiempo ?? 0}
              </p>
              <p className="mt-2 text-sm text-slate-600">
                Cumplimientos aprobados cuya fecha real supera la fecha hasta de la asignación. Siguen
                contando como ejecutadas; no reducen el % de cobertura (penalización pendiente de
                definición). No es un noveno KPI adicional.
              </p>
              <Link
                href="/reportes?tipo=cumplimiento_general"
                prefetch={false}
                className="mt-2 inline-block text-sm font-medium text-hseq-800 underline-offset-2 hover:underline"
              >
                Analizar en Reportes
              </Link>
            </Card>
          </section>

          <section className="mb-8">
            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
              Eficacia
            </h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <TarjetaEficacia
                titulo="Eficacia general"
                descripcion="Promedio de calificaciones válidas. Sin evaluación no cuenta como cero."
                kpi={eficacia.general}
              />
              <TarjetaEficacia
                titulo="Inducción y reinducción"
                descripcion="Promedio exclusivo de evaluaciones de inducción/reinducción."
                kpi={eficacia.induccion}
              />
              <TarjetaEficacia
                titulo="Tareas críticas"
                descripcion="Promedio exclusivo de evaluaciones en tareas críticas."
                kpi={eficacia.tareas_criticas}
              />
            </div>
          </section>

          <section className="mb-8">
            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
              Soportes
            </h2>
            <div className="max-w-xl">
              <TarjetaSoportes kpi={resumen.soportes} />
            </div>
          </section>

          <section className="mb-8">
            <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
              Horas de capacitación
            </h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <TarjetaHoras titulo="Total" kpi={horas.general} href="/reportes?tipo=horas" />
              <TarjetaHoras
                titulo="Inducción y reinducción"
                kpi={horas.induccion}
                href="/reportes?tipo=horas"
              />
              <TarjetaHoras
                titulo="Tareas críticas"
                kpi={horas.critica}
                href="/reportes?tipo=tareas_criticas"
              />
            </div>
          </section>
        </>
      ) : null}
    </>
  );
}
