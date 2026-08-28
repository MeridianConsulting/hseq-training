"use client";

import { useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";
import type { RegistroAuditoria } from "@/lib/tipos";

export default function AuditoriaPage() {
  return (
    <RequierePermiso permiso="auditoria.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [items, setItems] = useState<RegistroAuditoria[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [entidad, setEntidad] = useState("");
  const [accion, setAccion] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function cargar(paginaActual = 1) {
    const r = await apiGet<ListaPaginada<RegistroAuditoria>>(
      withQuery("/api/auditoria", { page: paginaActual, per_page: 20, entidad, accion }),
    );
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible cargar la auditoría.");
      return;
    }
    setItems(r.data.items);
    setPagina(r.data.pagination.current_page);
    setUltima(r.data.pagination.last_page);
    setError(null);
  }

  useEffect(() => {
    void cargar(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <>
      <PageHeader
        titulo="Auditoría"
        descripcion="Registro de altas, cambios y bajas del módulo HSEQ."
      />
      {error ? <Alert tono="error">{error}</Alert> : null}
      <Filters>
        <Field etiqueta="Entidad">
          <input className={inputClass} value={entidad} onChange={(e) => setEntidad(e.target.value)} />
        </Field>
        <Field etiqueta="Acción">
          <input className={inputClass} value={accion} onChange={(e) => setAccion(e.target.value)} />
        </Field>
        <div className="flex items-end">
          <Button type="button" variante="secondary" onClick={() => void cargar(1)}>
            Filtrar
          </Button>
        </div>
      </Filters>
      <Table
        columnas={[
          { clave: "fecha", etiqueta: "Fecha" },
          { clave: "usuario", etiqueta: "Usuario" },
          { clave: "accion", etiqueta: "Acción" },
          { clave: "entidad", etiqueta: "Entidad" },
          { clave: "id", etiqueta: "Id" },
        ]}
        filas={items.map((item) => [
          item.created_at ?? "—",
          item.nombre_usuario ?? "—",
          item.accion,
          item.entidad ?? "—",
          item.entidad_id ?? "—",
        ])}
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />
    </>
  );
}
