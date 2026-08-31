"use client";

import { useEffect, useState } from "react";
import { BloqueMesCronograma } from "@/components/cronograma/bloque-mes";
import { FiltroCronograma, type FiltroCronogramaValor } from "@/components/cronograma/filtro-cronograma";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { PageHeader } from "@/components/ui/page-header";
import { apiGet, withQuery } from "@/lib/api";
import type { TableroCronograma } from "@/lib/tipos";

function filtroInicial(): FiltroCronogramaValor {
  const hoy = new Date();
  return {
    tipo: "mensual",
    anio: hoy.getFullYear(),
    mes: hoy.getMonth() + 1,
    trimestre: Math.ceil((hoy.getMonth() + 1) / 3),
    procesoId: "",
  };
}

export default function CronogramaPage() {
  return (
    <RequierePermiso permiso="planes.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [filtro, setFiltro] = useState<FiltroCronogramaValor>(filtroInicial);
  const [tablero, setTablero] = useState<TableroCronograma | null>(null);
  const [mesAbierto, setMesAbierto] = useState<number | null>(null);
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
      if (filtro.procesoId) {
        params.proceso_id = Number(filtro.procesoId);
      }

      const respuesta = await apiGet<TableroCronograma>(withQuery("/api/cronograma", params));

      if (abortado.actual) {
        return;
      }

      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar el cronograma.");
        setTablero(null);
        setCargando(false);
        return;
      }

      setTablero(respuesta.data);
      setMesAbierto(filtro.tipo === "mensual" ? respuesta.data.meses[0]?.mes ?? null : null);
      setCargando(false);
    })();

    return () => {
      abortado.actual = true;
    };
  }, [filtro]);

  const desplegable = filtro.tipo !== "mensual";
  const procesos = tablero?.procesos ?? [];

  return (
    <>
      <PageHeader
        titulo="Tablero de Cronograma"
        descripcion="Capacitaciones del plan anual aprobado, agrupadas por mes."
      />

      <FiltroCronograma valor={filtro} procesos={procesos} onChange={setFiltro} />

      {error ? <Alert tono="error">{error}</Alert> : null}

      {cargando && !tablero ? (
        <p className="mb-4 text-sm text-slate-500">Cargando cronograma…</p>
      ) : null}

      {tablero ? (
        <>
          <p className="mb-4 text-sm text-slate-500">
            <span className="font-medium text-hseq-900">{tablero.periodo.etiqueta}</span>
            {tablero.proceso_nombre ? ` · ${tablero.proceso_nombre}` : " · Todos los procesos"}
            {" · "}
            {tablero.total === 0
              ? "0 capacitaciones programadas"
              : `${tablero.total} capacitación${tablero.total === 1 ? "" : "es"} programada${tablero.total === 1 ? "" : "s"}`}
            {tablero.estado_plan ? ` · Plan ${tablero.estado_plan === "APROBADO" ? "aprobado" : tablero.estado_plan}` : ""}
            {cargando ? " · Actualizando…" : null}
          </p>

          {tablero.total === 0 ? (
            <Alert tono="info">No hay capacitaciones programadas para este período.</Alert>
          ) : null}

          {tablero.total > 0 || filtro.tipo !== "mensual" ? (
            <div className={`space-y-4 ${tablero.total === 0 ? "mt-4" : ""}`}>
              {tablero.meses.map((bloque) => (
                <BloqueMesCronograma
                  key={`${tablero.periodo.tipo}-${tablero.periodo.anio}-${tablero.proceso_id ?? "todos"}-${bloque.mes}`}
                  bloque={bloque}
                  desplegable={desplegable}
                  abierto={mesAbierto === bloque.mes}
                  onToggle={() =>
                    setMesAbierto((actual) => (actual === bloque.mes ? null : bloque.mes))
                  }
                />
              ))}
            </div>
          ) : null}
        </>
      ) : null}
    </>
  );
}
