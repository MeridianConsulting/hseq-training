"use client";

import { FormEvent, useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type { Capacitacion, CargoCorporativo, FilaMatriz, ItemCatalogo } from "@/lib/tipos";
import { FormularioMatriz, type DatosMatriz } from "./formulario";

export default function MatrizPage() {
  return (
    <RequierePermiso permiso="matriz.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [items, setItems] = useState<FilaMatriz[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [capacitacionId, setCapacitacionId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [editando, setEditando] = useState<FilaMatriz | null>(null);
  const [capacitaciones, setCapacitaciones] = useState<Capacitacion[]>([]);
  const [cargos, setCargos] = useState<CargoCorporativo[]>([]);
  const [areas, setAreas] = useState<ItemCatalogo[]>([]);
  const [procesos, setProcesos] = useState<ItemCatalogo[]>([]);
  const [periodicidades, setPeriodicidades] = useState<ItemCatalogo[]>([]);

  async function cargar(paginaActual = pagina) {
    const respuesta = await apiGet<ListaPaginada<FilaMatriz>>(
      withQuery("/api/matriz", {
        page: paginaActual,
        per_page: 15,
        capacitacion_id: capacitacionId || undefined,
      }),
    );

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar la matriz.");
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
      const [caps, car, ar, pr, pe] = await Promise.all([
        apiGet<ListaPaginada<Capacitacion>>(withQuery("/api/capacitaciones", { per_page: 100, estado: "ACTIVA" })),
        apiGet<CargoCorporativo[]>("/api/personal/cargos"),
        apiGet<{ items: ItemCatalogo[] }>("/api/catalogs/areas"),
        apiGet<{ items: ItemCatalogo[] }>("/api/catalogs/procesos"),
        apiGet<{ items: ItemCatalogo[] }>("/api/catalogs/periodicidades?activos=1"),
      ]);
      setCapacitaciones(caps.data?.items ?? []);
      setCargos(car.data ?? []);
      setAreas(ar.data?.items ?? []);
      setProcesos(pr.data?.items ?? []);
      setPeriodicidades(pe.data?.items ?? []);
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function guardar(evento: FormEvent, datos: DatosMatriz) {
    evento.preventDefault();
    const n = (valor: string) => (valor === "" ? null : Number(valor));
    const cuerpo = {
      capacitacion_id: Number(datos.capacitacion_id),
      cargo_id_ext: n(datos.cargo_id_ext),
      area_id: n(datos.area_id),
      proceso_id: n(datos.proceso_id),
      ambito: datos.ambito || null,
      proyecto: datos.proyecto || null,
      periodicidad_id: n(datos.periodicidad_id),
      obligatoria: datos.obligatoria ? 1 : 0,
      activa: datos.activa ? 1 : 0,
    };

    const respuesta = editando
      ? await apiPut<FilaMatriz>(`/api/matriz/${editando.matriz_aplicabilidad_id}`, cuerpo)
      : await apiPost<FilaMatriz>("/api/matriz", cuerpo);

    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo guardar.");
      return;
    }

    setMensaje(respuesta.message);
    setAbierto(false);
    setEditando(null);
    await cargar();
  }

  async function eliminar(item: FilaMatriz) {
    if (!confirm("¿Eliminar esta fila de la matriz?")) {
      return;
    }

    const respuesta = await apiDelete(`/api/matriz/${item.matriz_aplicabilidad_id}`);
    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo eliminar.");
      return;
    }

    setMensaje(respuesta.message);
    await cargar();
  }

  return (
    <>
      <PageHeader
        titulo="Matriz de aplicabilidad"
        descripcion="Define qué capacitaciones aplican a cada cargo de meridian_personal. El cargo se guarda como referencia lógica."
        acciones={
          puede("matriz.crear") ? (
            <Button
              type="button"
              onClick={() => {
                setEditando(null);
                setAbierto(true);
              }}
            >
              Nueva fila
            </Button>
          ) : null
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <Filters>
        <Field etiqueta="Capacitación">
          <select className={inputClass} value={capacitacionId} onChange={(e) => setCapacitacionId(e.target.value)}>
            <option value="">Todas</option>
            {capacitaciones.map((c) => (
              <option key={c.capacitacion_id} value={c.capacitacion_id}>
                {c.codigo} — {c.nombre}
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
          { clave: "cap", etiqueta: "Capacitación" },
          { clave: "cargo", etiqueta: "Cargo" },
          { clave: "ambito", etiqueta: "Ámbito" },
          { clave: "proyecto", etiqueta: "Proyecto" },
          { clave: "flags", etiqueta: "Condición" },
          { clave: "acciones", etiqueta: "" },
        ]}
        filas={items.map((item) => [
          <div key="c">
            <p className="font-medium">{item.capacitacion_codigo}</p>
            <p className="text-xs text-slate-500">{item.capacitacion_nombre}</p>
          </div>,
          item.cargo_nombre ?? (item.cargo_id_ext ? `Cargo ${item.cargo_id_ext}` : "Cualquier cargo"),
          item.ambito ?? "—",
          item.proyecto ?? "—",
          <div key="f" className="flex gap-1">
            <Badge tono={item.obligatoria ? "alto" : "neutral"}>{item.obligatoria ? "Obligatoria" : "Opcional"}</Badge>
            <Badge tono={item.activa ? "ok" : "neutral"}>{item.activa ? "Activa" : "Inactiva"}</Badge>
          </div>,
          <div key="a" className="flex justify-end gap-2">
            {puede("matriz.editar") ? (
              <Button
                type="button"
                variante="ghost"
                onClick={() => {
                  setEditando(item);
                  setAbierto(true);
                }}
              >
                Editar
              </Button>
            ) : null}
            {puede("matriz.eliminar") ? (
              <Button type="button" variante="ghost" onClick={() => void eliminar(item)}>
                Eliminar
              </Button>
            ) : null}
          </div>,
        ])}
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />

      <Modal
        abierto={abierto}
        titulo={editando ? "Editar fila de matriz" : "Nueva fila de matriz"}
        onCerrar={() => setAbierto(false)}
      >
        <FormularioMatriz
          key={editando?.matriz_aplicabilidad_id ?? "nueva"}
          inicial={editando}
          capacitaciones={capacitaciones}
          cargos={cargos}
          areas={areas}
          procesos={procesos}
          periodicidades={periodicidades}
          onCancelar={() => setAbierto(false)}
          onGuardar={guardar}
        />
      </Modal>
    </>
  );
}
