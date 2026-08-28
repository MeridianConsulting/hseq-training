"use client";

import { useEffect, useState } from "react";
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
import type { CargoCorporativo, PersonaCorporativa } from "@/lib/tipos";

export default function PersonalPage() {
  return (
    <RequierePermiso permiso="personal.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [items, setItems] = useState<PersonaCorporativa[]>([]);
  const [cargos, setCargos] = useState<CargoCorporativo[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [buscar, setBuscar] = useState("");
  const [estado, setEstado] = useState("Activo");
  const [cargoId, setCargoId] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function cargar(paginaActual = 1) {
    const respuesta = await apiGet<ListaPaginada<PersonaCorporativa>>(
      withQuery("/api/personal", {
        page: paginaActual,
        per_page: 15,
        buscar,
        estado,
        cargo_id: cargoId || undefined,
      }),
    );

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible consultar el personal corporativo.");
      return;
    }

    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  useEffect(() => {
    void cargar(1);
    void (async () => {
      const r = await apiGet<CargoCorporativo[]>("/api/personal/cargos");
      if (r.success && r.data) {
        setCargos(r.data);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <>
      <PageHeader
        titulo="Personal corporativo"
        descripcion="Consulta de solo lectura sobre meridian_personal. No se copian personas a capacitaciones."
      />

      {error ? <Alert tono="error">{error}</Alert> : null}

      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={buscar}
            onChange={(e) => setBuscar(e.target.value)}
            placeholder="Documento o nombre"
          />
        </Field>
        <Field etiqueta="Estado laboral">
          <select className={inputClass} value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todos</option>
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
        </Field>
        <Field etiqueta="Cargo">
          <select className={inputClass} value={cargoId} onChange={(e) => setCargoId(e.target.value)}>
            <option value="">Todos</option>
            {cargos.map((c) => (
              <option key={c.cargo_id} value={c.cargo_id}>
                {c.nombre_cargo}
              </option>
            ))}
          </select>
        </Field>
        <div className="flex items-end">
          <Button type="button" variante="secondary" onClick={() => void cargar(1)}>
            Filtrar
          </Button>
        </div>
      </Filters>

      <Table
        columnas={[
          { clave: "doc", etiqueta: "Documento" },
          { clave: "nombre", etiqueta: "Nombre" },
          { clave: "cargo", etiqueta: "Cargo" },
          { clave: "proyecto", etiqueta: "Proyecto" },
          { clave: "estado", etiqueta: "Estado" },
        ]}
        filas={items.map((item) => [
          item.numero_documento,
          item.nombre_completo,
          item.cargo ?? "—",
          item.proyecto ?? "—",
          <Badge key="e" tono={item.estado === "Activo" ? "ok" : "neutral"}>
            {item.estado}
          </Badge>,
        ])}
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />
    </>
  );
}
