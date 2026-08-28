"use client";

import { FormEvent, useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Filters } from "@/components/ui/filters";
import { Field, inputClass } from "@/components/ui/field";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type { Capacitacion, ItemCatalogo } from "@/lib/tipos";
import { FormularioCapacitacion, type DatosCapacitacion } from "./formulario";

export default function CapacitacionesPage() {
  return (
    <RequierePermiso permiso="capacitaciones.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [items, setItems] = useState<Capacitacion[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [buscar, setBuscar] = useState("");
  const [estado, setEstado] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [editando, setEditando] = useState<Capacitacion | null>(null);
  const [catalogos, setCatalogos] = useState<Record<string, ItemCatalogo[]>>({});

  async function cargar(paginaActual = pagina) {
    const respuesta = await apiGet<ListaPaginada<Capacitacion>>(
      withQuery("/api/capacitaciones", { page: paginaActual, per_page: 15, buscar, estado }),
    );

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar las capacitaciones.");
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
      const tipos = ["categorias", "tipos-capacitacion", "proveedores", "periodicidades", "vigencias", "modalidades", "fuentes-normativas"];
      const entradas = await Promise.all(
        tipos.map(async (tipo) => {
          const r = await apiGet<{ items: ItemCatalogo[] }>(`/api/catalogs/${tipo}?activos=1`);
          return [tipo, r.data?.items ?? []] as const;
        }),
      );
      setCatalogos(Object.fromEntries(entradas));
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function guardar(evento: FormEvent, datos: DatosCapacitacion) {
    evento.preventDefault();
    const n = (valor: string) => (valor === "" ? null : Number(valor));
    const cuerpo = {
      ...datos,
      categoria_id: n(datos.categoria_id),
      tipo_capacitacion_id: n(datos.tipo_capacitacion_id),
      duracion_estimada_horas: Number(datos.duracion_estimada_horas),
      proveedor_default_id: n(datos.proveedor_default_id),
      periodicidad_default_id: n(datos.periodicidad_default_id),
      vigencia_id: n(datos.vigencia_id),
      modalidad_default_id: n(datos.modalidad_default_id),
      fuente_normativa_id: n(datos.fuente_normativa_id),
      nota_minima: datos.nota_minima === "" ? 0 : Number(datos.nota_minima),
      es_tarea_critica: datos.es_tarea_critica ? 1 : 0,
      evaluacion: datos.evaluacion ? 1 : 0,
      certificado: datos.certificado ? 1 : 0,
      requiere_listado_asistencia: datos.requiere_listado_asistencia ? 1 : 0,
    };

    const respuesta = editando
      ? await apiPut<Capacitacion>(`/api/capacitaciones/${editando.capacitacion_id}`, cuerpo)
      : await apiPost<Capacitacion>("/api/capacitaciones", cuerpo);

    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo guardar.");
      return;
    }

    setMensaje(respuesta.message);
    setAbierto(false);
    setEditando(null);
    await cargar();
  }

  async function eliminar(item: Capacitacion) {
    if (!confirm(`¿Eliminar o inactivar "${item.codigo}"?`)) {
      return;
    }

    const respuesta = await apiDelete(`/api/capacitaciones/${item.capacitacion_id}`);
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
        titulo="Capacitaciones"
        descripcion="Catálogo del programa. Periodicidad (ciclo) y vigencia (validez del curso tomado) son independientes."
        acciones={
          puede("capacitaciones.crear") ? (
            <Button
              type="button"
              onClick={() => {
                setEditando(null);
                setAbierto(true);
              }}
            >
              Nueva capacitación
            </Button>
          ) : null
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={buscar}
            onChange={(e) => setBuscar(e.target.value)}
            placeholder="Código o nombre"
          />
        </Field>
        <Field etiqueta="Estado">
          <select className={inputClass} value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todas</option>
            <option value="ACTIVA">Activa</option>
            <option value="INACTIVA">Inactiva</option>
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
          { clave: "codigo", etiqueta: "Código" },
          { clave: "nombre", etiqueta: "Nombre" },
          { clave: "criticidad", etiqueta: "Criticidad" },
          { clave: "periodicidad", etiqueta: "Periodicidad" },
          { clave: "vigencia", etiqueta: "Vigencia" },
          { clave: "estado", etiqueta: "Estado" },
          { clave: "acciones", etiqueta: "" },
        ]}
        filas={items.map((item) => [
          item.codigo,
          <div key="n">
            <p className="font-medium">{item.nombre}</p>
            {item.es_tarea_critica ? <Badge tono="alto">Tarea crítica</Badge> : null}
          </div>,
          item.criticidad,
          item.periodicidad_nombre ?? "—",
          item.vigencia_nombre ?? "No vence",
          <Badge key="e" tono={item.estado === "ACTIVA" ? "ok" : "neutral"}>
            {item.estado === "ACTIVA" ? "Activa" : "Inactiva"}
          </Badge>,
          <div key="a" className="flex justify-end gap-2">
            {puede("capacitaciones.editar") ? (
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
            {puede("capacitaciones.eliminar") ? (
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
        titulo={editando ? "Editar capacitación" : "Nueva capacitación"}
        onCerrar={() => setAbierto(false)}
      >
        <FormularioCapacitacion
          key={editando?.capacitacion_id ?? "nueva"}
          inicial={editando}
          catalogos={catalogos}
          onCancelar={() => setAbierto(false)}
          onGuardar={guardar}
        />
      </Modal>
    </>
  );
}
