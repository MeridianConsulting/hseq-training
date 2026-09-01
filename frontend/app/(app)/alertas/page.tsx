"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";
import type { AlertaProximaVencer, OpcionesAlertas } from "@/lib/tipos";

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function etiquetaDias(dias: number): string {
  return dias === 1 ? "1 día" : `${dias} días`;
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
  const [procesoId, setProcesoId] = useState("");
  const [proyecto, setProyecto] = useState("");
  const [cargoId, setCargoId] = useState("");
  const [opciones, setOpciones] = useState<OpcionesAlertas>({
    procesos: [],
    proyectos: [],
    cargos: [],
  });
  const [error, setError] = useState<string | null>(null);

  async function cargar(paginaActual = 1) {
    const respuesta = await apiGet<ListaPaginada<AlertaProximaVencer>>(
      withQuery("/api/alertas", {
        page: paginaActual,
        per_page: 15,
        proceso_id: procesoId || undefined,
        proyecto: proyecto || undefined,
        cargo_id_ext: cargoId || undefined,
      }),
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar las alertas. Intente nuevamente.");
      return;
    }
    setItems(respuesta.data.items);
    setTotal(respuesta.data.pagination.total);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  function limpiarFiltros() {
    setProcesoId("");
    setProyecto("");
    setCargoId("");
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
    void cargar(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [procesoId, proyecto, cargoId]);

  return (
    <>
      <PageHeader
        titulo="Alertas"
        descripcion={`Capacitaciones próximas a vencer: ${total}`}
      />
      {error ? <Alert tono="error">{error}</Alert> : null}
      <Filters>
        <Field etiqueta="Proceso">
          <select
            className={inputClass}
            value={procesoId}
            onChange={(e) => setProcesoId(e.target.value)}
          >
            <option value="">Todos</option>
            {opciones.procesos.map((item) => (
              <option key={item.proceso_id} value={item.proceso_id}>
                {item.nombre}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Proyecto">
          <select
            className={inputClass}
            value={proyecto}
            onChange={(e) => setProyecto(e.target.value)}
          >
            <option value="">Todos</option>
            {opciones.proyectos.map((nombre) => (
              <option key={nombre} value={nombre}>
                {nombre}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Cargo">
          <select className={inputClass} value={cargoId} onChange={(e) => setCargoId(e.target.value)}>
            <option value="">Todos</option>
            {opciones.cargos.map((item) => (
              <option key={item.cargo_id} value={item.cargo_id}>
                {item.nombre_cargo}
              </option>
            ))}
          </select>
        </Field>
        <div className="flex items-end pb-0.5">
          <Button type="button" variante="secondary" onClick={limpiarFiltros}>
            Limpiar filtros
          </Button>
        </div>
      </Filters>
      <Table
        columnas={[
          { clave: "trabajador", etiqueta: "Trabajador" },
          { clave: "documento", etiqueta: "Documento" },
          { clave: "cargo", etiqueta: "Cargo" },
          { clave: "proceso", etiqueta: "Proceso" },
          { clave: "proyecto", etiqueta: "Proyecto" },
          { clave: "cap", etiqueta: "Capacitación" },
          { clave: "realizacion", etiqueta: "Realización" },
          { clave: "vence", etiqueta: "Vencimiento" },
          { clave: "dias", etiqueta: "Días restantes" },
          { clave: "estado", etiqueta: "Estado" },
          { clave: "acciones", etiqueta: "" },
        ]}
        filas={items.map((item) => [
          item.trabajador ?? (item.persona_id_ext ? `Persona ${item.persona_id_ext}` : "—"),
          item.documento ?? "—",
          item.cargo ?? "—",
          item.proceso ?? "—",
          item.proyecto ?? "—",
          etiquetaCapacitacion(item),
          formatoFecha(item.fecha_realizacion),
          formatoFecha(item.fecha_vencimiento),
          etiquetaDias(item.dias_restantes),
          <Badge key={`e-${item.cumplimiento_id}`} tono="aviso">
            Próximo a vencer
          </Badge>,
          item.persona_id_ext ? (
            <Link
              key={`h-${item.cumplimiento_id}`}
              href={rutaHistorial(item)}
              className="font-medium text-hseq-800 underline-offset-2 hover:underline"
            >
              Ver historial
            </Link>
          ) : (
            "—"
          ),
        ])}
        vacio="No hay capacitaciones próximas a vencer en los próximos 10 días."
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />
    </>
  );
}
