"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";
import type { EvidenciaFaltante } from "@/lib/tipos";

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function rutaHistorial(item: EvidenciaFaltante): string {
  return withQuery("/asignaciones", {
    persona_id: item.persona_id_ext ?? undefined,
    nombre: item.trabajador,
    documento: item.documento,
  });
}

export default function Page() {
  return (
    <RequierePermiso permiso="reportes.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [items, setItems] = useState<EvidenciaFaltante[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [buscar, setBuscar] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function cargar(paginaActual = 1) {
    const respuesta = await apiGet<ListaPaginada<EvidenciaFaltante>>(
      withQuery("/api/reportes/evidencias-faltantes", {
        page: paginaActual,
        per_page: 20,
        buscar: buscar.trim() || undefined,
      }),
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar el reporte.");
      return;
    }
    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  useEffect(() => {
    const id = window.setTimeout(() => {
      void cargar(1);
    }, 300);
    return () => window.clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [buscar]);

  return (
    <>
      <PageHeader
        titulo="Reportes"
        descripcion="Cumplimientos que requieren certificado y aún no tienen archivo adjunto."
      />
      {error ? <Alert tono="error">{error}</Alert> : null}
      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={buscar}
            onChange={(e) => setBuscar(e.target.value)}
            placeholder="Trabajador, documento o capacitación"
          />
        </Field>
      </Filters>
      <Table
        columnas={[
          { clave: "trabajador", etiqueta: "Trabajador" },
          { clave: "documento", etiqueta: "Documento" },
          { clave: "cap", etiqueta: "Capacitación" },
          { clave: "fecha", etiqueta: "Fecha de realización" },
          { clave: "estado", etiqueta: "Estado" },
          { clave: "cert", etiqueta: "Requiere certificado" },
          { clave: "cant", etiqueta: "Cantidad de evidencias" },
        ]}
        filas={items.map((item) => [
          item.persona_id_ext ? (
            <Link
              key={`p-${item.cumplimiento_id}`}
              href={rutaHistorial(item)}
              className="font-medium text-hseq-800 underline-offset-2 hover:underline"
            >
              {item.trabajador ?? `Persona ${item.persona_id_ext}`}
            </Link>
          ) : (
            (item.trabajador ?? "—")
          ),
          item.documento ?? "—",
          item.capacitacion ?? "—",
          formatoFecha(item.fecha_realizacion),
          item.estado,
          item.requiere_certificado ? "Sí" : "No",
          item.soportes_count,
        ])}
        vacio="No hay cumplimientos con evidencia faltante."
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />
    </>
  );
}
