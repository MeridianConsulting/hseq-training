"use client";

import { FormEvent, useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { useDebouncedCallback } from "@/hooks/useFiltrosUrl";
import { Pencil, Plus, RotateCcw, UserMinus } from "lucide-react";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import { detalleItemCatalogo } from "@/lib/catalogos";
import type { ItemCatalogo, TipoCatalogo } from "@/lib/tipos";
import { FormularioCatalogo } from "./formulario";

type FiltroEstado = "todos" | "activos" | "inactivos";

const ETIQUETAS_ESTADO: Record<FiltroEstado, string> = {
  todos: "Todos",
  activos: "Activos",
  inactivos: "Inactivos",
};

export default function ConfiguracionPage() {
  return (
    <RequierePermiso permiso="catalogos.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [tipos, setTipos] = useState<TipoCatalogo[]>([]);
  const [tipo, setTipo] = useState<TipoCatalogo | null>(null);
  const [items, setItems] = useState<ItemCatalogo[]>([]);
  const [buscar, setBuscar] = useState("");
  const [filtroEstado, setFiltroEstado] = useState<FiltroEstado>("todos");
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [editando, setEditando] = useState<ItemCatalogo | null>(null);

  useEffect(() => {
    void (async () => {
      const r = await apiGet<TipoCatalogo[]>("/api/catalogs");
      if (!r.success || !r.data) {
        setError(r.message || "No fue posible cargar los catálogos.");
        return;
      }
      setTipos(r.data);
      setTipo(r.data[0] ?? null);
    })();
  }, []);

  async function cargar(
    tipoActual: string,
    estado: FiltroEstado = filtroEstado,
    paginaActual = pagina,
    textoBuscar = buscar,
  ) {
    setCargando(true);
    const r = await apiGet<ListaPaginada<ItemCatalogo>>(
      withQuery(`/api/catalogs/${tipoActual}`, {
        estado,
        buscar: textoBuscar || undefined,
        page: paginaActual,
        per_page: 20,
      }),
    );
    setCargando(false);
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible cargar el catálogo.");
      return;
    }
    setItems(r.data.items);
    setPagina(r.data.pagination.current_page);
    setUltima(r.data.pagination.last_page);
    setError(null);
  }

  useDebouncedCallback(() => {
    if (!tipo) {
      return;
    }
    void cargar(tipo.tipo, filtroEstado, 1, buscar);
  }, [tipo?.tipo, filtroEstado, buscar]);

  function limpiarFiltros() {
    setBuscar("");
    setFiltroEstado("todos");
  }

  const chips: ChipFiltro[] = [];
  if (buscar) {
    chips.push({ clave: "buscar", etiqueta: "Buscar", valor: buscar });
  }
  if (filtroEstado !== "todos") {
    chips.push({
      clave: "estado",
      etiqueta: "Estado",
      valor: ETIQUETAS_ESTADO[filtroEstado],
    });
  }

  function quitarChip(clave: string) {
    if (clave === "buscar") setBuscar("");
    if (clave === "estado") setFiltroEstado("todos");
  }

  function pkDe(def: TipoCatalogo, item: ItemCatalogo): number | null {
    const clave = Object.keys(item).find((k) => k.endsWith("_id")) ?? "";
    const valor = item[clave];
    return typeof valor === "number" ? valor : valor != null ? Number(valor) : null;
  }

  function formatoFecha(valor: unknown): string {
    if (typeof valor !== "string" || valor === "") {
      return "—";
    }
    return valor.slice(0, 19).replace("T", " ");
  }

  async function guardar(evento: FormEvent, datos: Record<string, unknown>) {
    evento.preventDefault();
    if (!tipo) {
      return;
    }

    const id = editando ? pkDe(tipo, editando) : null;
    const respuesta = editando && id
      ? await apiPut<ItemCatalogo>(`/api/catalogs/${tipo.tipo}/${id}`, datos)
      : await apiPost<ItemCatalogo>(`/api/catalogs/${tipo.tipo}`, datos);

    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo guardar.");
      return;
    }

    setMensaje(respuesta.message);
    setError(null);
    setAbierto(false);
    setEditando(null);
    await cargar(tipo.tipo);
  }

  async function inactivar(item: ItemCatalogo) {
    if (!tipo || !confirm("¿Está seguro de inactivar este registro?")) {
      return;
    }
    const id = pkDe(tipo, item);
    if (!id) {
      return;
    }
    const r = await apiDelete(`/api/catalogs/${tipo.tipo}/${id}`);
    if (!r.success) {
      setError(r.message || "No se pudo inactivar.");
      return;
    }
    setMensaje(r.message);
    setError(null);
    await cargar(tipo.tipo);
  }

  async function reactivar(item: ItemCatalogo) {
    if (!tipo) {
      return;
    }
    const id = pkDe(tipo, item);
    if (!id) {
      return;
    }
    const r = await apiPut<ItemCatalogo>(`/api/catalogs/${tipo.tipo}/${id}`, { activo: 1 });
    if (!r.success) {
      setError(r.message || "No se pudo reactivar.");
      return;
    }
    setMensaje(r.message || "Registro reactivado");
    setError(null);
    await cargar(tipo.tipo);
  }

  return (
    <>
      <PageHeader
        titulo="Catálogos"
        descripcion="Parámetros del módulo HSEQ. Los inactivos se conservan en históricos y no aparecen en altas nuevas. Cargos y personal viven en meridian_personal."
        acciones={
          puede("catalogos.gestionar") && tipo ? (
            <Button
              type="button"
              onClick={() => {
                setEditando(null);
                setAbierto(true);
              }}
            >
              <Plus className="h-4 w-4" aria-hidden />
              Nuevo registro
            </Button>
          ) : null
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {tipos.map((t) => (
          <button
            key={t.tipo}
            type="button"
            onClick={() => {
              setTipo(t);
              setPagina(1);
            }}
            className={`rounded-full px-3 py-1 text-sm ${
              tipo?.tipo === t.tipo ? "bg-hseq-800 text-white" : "bg-white text-slate-600 ring-1 ring-slate-200"
            }`}
          >
            {t.etiqueta}
          </button>
        ))}
      </div>

      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={buscar}
            onChange={(e) => setBuscar(e.target.value)}
            placeholder="Nombre"
          />
        </Field>
        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={filtroEstado}
            onChange={(e) => setFiltroEstado(e.target.value as FiltroEstado)}
          >
            <option value="todos">Todos</option>
            <option value="activos">Activos</option>
            <option value="inactivos">Inactivos</option>
          </select>
        </Field>
      </Filters>

      <FiltrosActivos chips={chips} onQuitar={quitarChip} onLimpiar={limpiarFiltros} />

      {cargando ? (
        <ListaCargando />
      ) : (
        <Table
          columnas={[
            { clave: "nombre", etiqueta: "Nombre" },
            { clave: "extra", etiqueta: "Detalle" },
            { clave: "estado", etiqueta: "Estado" },
            { clave: "creado", etiqueta: "Creado" },
            { clave: "actualizado", etiqueta: "Actualizado" },
            { clave: "acciones", etiqueta: "" },
          ]}
          filas={items.map((item) => [
            String(item.nombre ?? ""),
            detalleItemCatalogo(item),
            item.activo === undefined ? (
              "—"
            ) : (
              <Badge tono={Number(item.activo) === 1 ? "ok" : "neutral"}>
                {Number(item.activo) === 1 ? "Activo" : "Inactivo"}
              </Badge>
            ),
            formatoFecha(item.created_at),
            formatoFecha(item.updated_at),
            <div key="a" className="flex justify-end gap-2">
              {puede("catalogos.gestionar") ? (
                <>
                  <Button
                    type="button"
                    variante="ghost"
                    onClick={() => {
                      setEditando(item);
                      setAbierto(true);
                    }}
                  >
                    <Pencil className="h-4 w-4" aria-hidden />
                    Editar
                  </Button>
                  {Number(item.activo) === 0 ? (
                    <Button type="button" variante="ghost" onClick={() => void reactivar(item)}>
                      <RotateCcw className="h-4 w-4" aria-hidden />
                      Reactivar
                    </Button>
                  ) : (
                    <Button type="button" variante="ghost" onClick={() => void inactivar(item)}>
                      <UserMinus className="h-4 w-4" aria-hidden />
                      Inactivar
                    </Button>
                  )}
                </>
              ) : null}
            </div>,
          ])}
        />
      )}

      {tipo ? (
        <Pagination
          pagina={pagina}
          ultima={ultima}
          onCambiar={(p) => void cargar(tipo.tipo, filtroEstado, p)}
        />
      ) : null}

      {tipo ? (
        <Modal
          abierto={abierto}
          titulo={editando ? `Editar ${tipo.etiqueta}` : `Nuevo en ${tipo.etiqueta}`}
          onCerrar={() => setAbierto(false)}
        >
          <FormularioCatalogo
            key={editando ? "editar" : "nuevo"}
            campos={tipo.campos}
            permiteActivo={tipo.permite_inactivar && Boolean(editando)}
            inicial={editando}
            onCancelar={() => setAbierto(false)}
            onGuardar={guardar}
          />
        </Modal>
      ) : null}
    </>
  );
}
