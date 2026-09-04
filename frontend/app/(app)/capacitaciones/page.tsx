"use client";

import { FormEvent, useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Filters } from "@/components/ui/filters";
import { Field, inputClass } from "@/components/ui/field";
import { FiltrosActivos, ListaCargando, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { useDebouncedCallback, useFiltrosUrl } from "@/hooks/useFiltrosUrl";
import {
  apiDelete,
  apiGet,
  apiPost,
  apiPut,
  withQuery,
  type ApiErrorMap,
  type ListaPaginada,
} from "@/lib/api";
import type { Capacitacion, ItemCatalogo } from "@/lib/tipos";
import { Plus, Pencil, Trash2 } from "lucide-react";
import { FormularioCapacitacion, type DatosCapacitacion } from "./formulario";

function siNo(valor: boolean): string {
  return valor ? "Sí" : "No";
}

function recortar(texto: string, max = 80): string {
  const limpio = texto.trim();
  if (limpio.length <= max) return limpio || "—";
  return `${limpio.slice(0, max).trimEnd()}…`;
}

export default function CapacitacionesPage() {
  return (
    <RequierePermiso permiso="capacitaciones.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const { valores, setFiltro, limpiar } = useFiltrosUrl({
    buscar: "",
    tipo_capacitacion_id: "",
    modalidad_default_id: "",
    es_tarea_critica: "",
    evaluacion: "",
    estado: "",
  });
  const [items, setItems] = useState<Capacitacion[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [editando, setEditando] = useState<Capacitacion | null>(null);
  const [catalogos, setCatalogos] = useState<Record<string, ItemCatalogo[]>>({});
  const [erroresApi, setErroresApi] = useState<ApiErrorMap | null>(null);

  async function cargar(paginaActual = pagina) {
    setCargando(true);
    const respuesta = await apiGet<ListaPaginada<Capacitacion>>(
      withQuery("/api/capacitaciones", {
        page: paginaActual,
        per_page: 15,
        buscar: valores.buscar || undefined,
        tipo_capacitacion_id: valores.tipo_capacitacion_id || undefined,
        modalidad_default_id: valores.modalidad_default_id || undefined,
        es_tarea_critica: valores.es_tarea_critica || undefined,
        evaluacion: valores.evaluacion || undefined,
        estado: valores.estado || undefined,
      }),
    );
    setCargando(false);

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar las capacitaciones.");
      return;
    }

    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  useDebouncedCallback(() => {
    void cargar(1);
  }, [
    valores.buscar,
    valores.tipo_capacitacion_id,
    valores.modalidad_default_id,
    valores.es_tarea_critica,
    valores.evaluacion,
    valores.estado,
  ]);

  useEffect(() => {
    void (async () => {
      const tipos = ["tipos-capacitacion", "vigencias", "modalidades"];
      const entradas = await Promise.all(
        tipos.map(async (tipo) => {
          const r = await apiGet<{ items: ItemCatalogo[] }>(`/api/catalogs/${tipo}?activos=1`);
          return [tipo, r.data?.items ?? []] as const;
        }),
      );
      setCatalogos(Object.fromEntries(entradas));
    })();
  }, []);

  const chips: ChipFiltro[] = [];
  if (valores.buscar) {
    chips.push({ clave: "buscar", etiqueta: "Buscar", valor: valores.buscar });
  }
  if (valores.tipo_capacitacion_id) {
    const tipo = (catalogos["tipos-capacitacion"] ?? []).find(
      (item) => String(item.tipo_capacitacion_id) === valores.tipo_capacitacion_id,
    );
    chips.push({
      clave: "tipo_capacitacion_id",
      etiqueta: "Tipo",
      valor: tipo ? String(tipo.nombre ?? valores.tipo_capacitacion_id) : valores.tipo_capacitacion_id,
    });
  }
  if (valores.modalidad_default_id) {
    const mod = (catalogos.modalidades ?? []).find(
      (item) => String(item.modalidad_id) === valores.modalidad_default_id,
    );
    chips.push({
      clave: "modalidad_default_id",
      etiqueta: "Modalidad",
      valor: mod ? String(mod.nombre ?? valores.modalidad_default_id) : valores.modalidad_default_id,
    });
  }
  if (valores.es_tarea_critica) {
    chips.push({
      clave: "es_tarea_critica",
      etiqueta: "Tarea crítica",
      valor: valores.es_tarea_critica === "1" ? "Sí" : "No",
    });
  }
  if (valores.evaluacion) {
    chips.push({
      clave: "evaluacion",
      etiqueta: "Requiere evaluación",
      valor: valores.evaluacion === "1" ? "Sí" : "No",
    });
  }
  if (valores.estado) {
    chips.push({
      clave: "estado",
      etiqueta: "Estado",
      valor: valores.estado === "ACTIVA" ? "Activa" : valores.estado === "INACTIVA" ? "Inactiva" : valores.estado,
    });
  }

  function abrirNueva() {
    setEditando(null);
    setErroresApi(null);
    setError(null);
    setAbierto(true);
  }

  function abrirEdicion(item: Capacitacion) {
    setEditando(item);
    setErroresApi(null);
    setError(null);
    setAbierto(true);
  }

  async function guardar(evento: FormEvent, datos: DatosCapacitacion) {
    evento.preventDefault();
    setErroresApi(null);

    const horas = datos.duracion_estimada_horas.trim();
    if (horas === "" || !Number.isFinite(Number(horas)) || Number(horas) <= 0) {
      setError("No fue posible guardar la capacitación. Verifique la información ingresada.");
      return;
    }

    const n = (valor: string) => (valor === "" ? null : Number(valor));
    const cuerpo = {
      codigo: datos.codigo.trim(),
      nombre: datos.nombre.trim(),
      objetivo: datos.objetivo.trim(),
      tipo_capacitacion_id: n(datos.tipo_capacitacion_id),
      duracion_estimada_horas: Number(horas),
      vigencia_id: n(datos.vigencia_id),
      modalidad_default_id: n(datos.modalidad_default_id),
      nota_minima: datos.evaluacion && datos.nota_minima !== "" ? Number(datos.nota_minima) : 0,
      es_tarea_critica: datos.es_tarea_critica ? 1 : 0,
      evaluacion: datos.evaluacion ? 1 : 0,
      certificado: datos.certificado ? 1 : 0,
      requiere_listado_asistencia: datos.requiere_listado_asistencia ? 1 : 0,
      estado: datos.estado,
    };

    const respuesta = editando
      ? await apiPut<Capacitacion>(`/api/capacitaciones/${editando.capacitacion_id}`, cuerpo)
      : await apiPost<Capacitacion>("/api/capacitaciones", cuerpo);

    if (!respuesta.success) {
      if (respuesta.errors) {
        setErroresApi(respuesta.errors);
        setError(respuesta.message || "No fue posible guardar la capacitación.");
      } else {
        setError(respuesta.message || "No fue posible guardar la capacitación.");
      }
      return;
    }

    setMensaje(respuesta.message || (editando ? "Capacitación actualizada correctamente." : "Capacitación creada correctamente."));
    setError(null);
    setErroresApi(null);
    setAbierto(false);
    setEditando(null);
    await cargar();
  }

  async function eliminar(item: Capacitacion) {
    if (!confirm(`¿Eliminar o desactivar "${item.codigo}"? Si tiene historial se inactivará.`)) {
      return;
    }

    const respuesta = await apiDelete(`/api/capacitaciones/${item.capacitacion_id}`);
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible eliminar la capacitación.");
      return;
    }

    setMensaje(respuesta.message);
    await cargar();
  }

  return (
    <>
      <PageHeader
        titulo="Capacitaciones"
        descripcion="Catálogo maestro del programa. Los demás módulos reutilizan estas capacitaciones."
        acciones={
          puede("capacitaciones.crear") ? (
            <Button type="button" onClick={abrirNueva}>
              <Plus className="h-4 w-4" aria-hidden />
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
            value={valores.buscar}
            onChange={(e) => setFiltro("buscar", e.target.value)}
            placeholder="Código o nombre"
          />
        </Field>
        <Field etiqueta="Tipo">
          <select
            className={inputClass}
            value={valores.tipo_capacitacion_id}
            onChange={(e) => setFiltro("tipo_capacitacion_id", e.target.value)}
          >
            <option value="">Todos</option>
            {(catalogos["tipos-capacitacion"] ?? []).map((item) => (
              <option key={String(item.tipo_capacitacion_id)} value={String(item.tipo_capacitacion_id)}>
                {String(item.nombre ?? "")}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Modalidad">
          <select
            className={inputClass}
            value={valores.modalidad_default_id}
            onChange={(e) => setFiltro("modalidad_default_id", e.target.value)}
          >
            <option value="">Todas</option>
            {(catalogos.modalidades ?? []).map((item) => (
              <option key={String(item.modalidad_id)} value={String(item.modalidad_id)}>
                {String(item.nombre ?? "")}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Tarea crítica">
          <select
            className={inputClass}
            value={valores.es_tarea_critica}
            onChange={(e) => setFiltro("es_tarea_critica", e.target.value)}
          >
            <option value="">Todas</option>
            <option value="1">Sí</option>
            <option value="0">No</option>
          </select>
        </Field>
        <Field etiqueta="Requiere evaluación">
          <select
            className={inputClass}
            value={valores.evaluacion}
            onChange={(e) => setFiltro("evaluacion", e.target.value)}
          >
            <option value="">Todas</option>
            <option value="1">Sí</option>
            <option value="0">No</option>
          </select>
        </Field>
        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={valores.estado}
            onChange={(e) => setFiltro("estado", e.target.value)}
          >
            <option value="">Todas</option>
            <option value="ACTIVA">Activa</option>
            <option value="INACTIVA">Inactiva</option>
          </select>
        </Field>
      </Filters>

      <FiltrosActivos
        chips={chips}
        onQuitar={(clave) => setFiltro(clave, "")}
        onLimpiar={limpiar}
      />

      {cargando ? (
        <ListaCargando />
      ) : (
        <Table
          columnas={[
            { clave: "codigo", etiqueta: "Código" },
            { clave: "nombre", etiqueta: "Nombre" },
            { clave: "objetivo", etiqueta: "Objetivo" },
            { clave: "duracion", etiqueta: "Duración (h)" },
            { clave: "tipo", etiqueta: "Tipo" },
            { clave: "modalidad", etiqueta: "Modalidad" },
            { clave: "vigencia", etiqueta: "Vigencia" },
            { clave: "critica", etiqueta: "Tarea crítica" },
            { clave: "evaluacion", etiqueta: "Evaluación" },
            { clave: "nota", etiqueta: "Nota mínima" },
            { clave: "asistencia", etiqueta: "Asistencia" },
            { clave: "certificado", etiqueta: "Certificado" },
            { clave: "estado", etiqueta: "Estado" },
            { clave: "acciones", etiqueta: "" },
          ]}
          filas={items.map((item) => [
            item.codigo,
            item.nombre,
            recortar(item.objetivo ?? ""),
            item.duracion_estimada_horas,
            item.tipo_nombre ?? "—",
            item.modalidad_nombre ?? "—",
            item.vigencia_nombre ?? "No vence",
            siNo(item.es_tarea_critica),
            siNo(item.evaluacion),
            item.evaluacion ? (item.nota_minima ?? "—") : "No aplica",
            siNo(item.requiere_listado_asistencia),
            siNo(item.certificado),
            <Badge key="e" tono={item.estado === "ACTIVA" ? "ok" : "neutral"}>
              {item.estado === "ACTIVA" ? "Activa" : "Inactiva"}
            </Badge>,
            <div key="a" className="flex justify-end gap-2">
              {puede("capacitaciones.editar") ? (
                <Button type="button" variante="ghost" onClick={() => abrirEdicion(item)}>
                  <Pencil className="h-4 w-4" aria-hidden />
                  Editar
                </Button>
              ) : null}
              {puede("capacitaciones.eliminar") ? (
                <Button type="button" variante="ghost" onClick={() => void eliminar(item)}>
                  <Trash2 className="h-4 w-4" aria-hidden />
                  Eliminar / desactivar
                </Button>
              ) : null}
            </div>,
          ])}
          vacio="No hay capacitaciones para los filtros seleccionados."
        />
      )}
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
          erroresApi={erroresApi}
          onCancelar={() => setAbierto(false)}
          onGuardar={guardar}
        />
      </Modal>
    </>
  );
}
