"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { procesoPermiteFiltroProyecto } from "@/components/dashboard/filtro-periodo";
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
import { useDebouncedCallback, useFiltrosUrl } from "@/hooks/useFiltrosUrl";
import { IdCard, Pencil } from "lucide-react";
import { apiGet, apiPut, withQuery, type ApiErrorMap, type ListaPaginada } from "@/lib/api";
import type { OpcionesPersonal, PersonaCorporativa, TipoDocumentoCorporativo } from "@/lib/tipos";
import { FormularioTrabajador, type DatosTrabajador } from "./formulario";

function etiquetaProcesos(item: PersonaCorporativa): string {
  if (item.proceso_nombre) {
    return item.proceso_nombre;
  }
  const nombres = (item.procesos ?? []).map((p) => p.nombre).filter(Boolean);
  return nombres.length > 0 ? nombres.join(", ") : "—";
}

export default function PersonalPage() {
  return (
    <RequierePermiso permiso="personal.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const { valores, setFiltro, limpiar } = useFiltrosUrl({
    buscar: "",
    estado: "Activo",
    cargo_id: "",
    proceso_id: "",
    proyecto: "",
  });
  const [items, setItems] = useState<PersonaCorporativa[]>([]);
  const [opciones, setOpciones] = useState<OpcionesPersonal>({
    procesos: [],
    proyectos: [],
    cargos: [],
  });
  const [tiposDocumento, setTiposDocumento] = useState<TipoDocumentoCorporativo[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [opcionesListas, setOpcionesListas] = useState(false);
  const [editando, setEditando] = useState<PersonaCorporativa | null>(null);
  const [erroresApi, setErroresApi] = useState<ApiErrorMap | null>(null);

  const muestraProyecto = procesoPermiteFiltroProyecto(valores.proceso_id, opciones.procesos);

  async function cargar(paginaActual = 1) {
    setCargando(true);
    const respuesta = await apiGet<ListaPaginada<PersonaCorporativa>>(
      withQuery("/api/personal", {
        page: paginaActual,
        per_page: 15,
        buscar: valores.buscar,
        estado: valores.estado,
        cargo_id: valores.cargo_id || undefined,
        proceso_id: valores.proceso_id || undefined,
        proyecto: muestraProyecto ? valores.proyecto || undefined : undefined,
      }),
    );
    setCargando(false);

    if (respuesta.cancelada) {
      return;
    }
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "Error al obtener la información del sistema corporativo.");
      return;
    }

    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  useEffect(() => {
    void (async () => {
      const [rOpciones, rTipos] = await Promise.all([
        apiGet<OpcionesPersonal>("/api/personal/opciones"),
        apiGet<TipoDocumentoCorporativo[]>("/api/personal/tipos-documento"),
      ]);
      if (rOpciones.success && rOpciones.data) {
        setOpciones(rOpciones.data);
      }
      if (rTipos.success && rTipos.data) {
        setTiposDocumento(rTipos.data);
      }
      setOpcionesListas(true);
    })();
  }, []);

  useEffect(() => {
    if (!opcionesListas) {
      return;
    }
    if (!muestraProyecto && valores.proyecto) {
      setFiltro("proyecto", "");
    }
  }, [opcionesListas, muestraProyecto, valores.proyecto, setFiltro]);

  useDebouncedCallback(() => {
    if (!opcionesListas) {
      return;
    }
    void cargar(1);
  }, [opcionesListas, valores.buscar, valores.estado, valores.cargo_id, valores.proceso_id, valores.proyecto]);

  const chips: ChipFiltro[] = [];
  if (valores.buscar) {
    chips.push({ clave: "buscar", etiqueta: "Buscar", valor: valores.buscar });
  }
  if (valores.estado && valores.estado !== "Activo") {
    chips.push({ clave: "estado", etiqueta: "Estado", valor: valores.estado });
  }
  if (valores.cargo_id) {
    const cargo = opciones.cargos.find((c) => String(c.cargo_id) === valores.cargo_id);
    chips.push({
      clave: "cargo_id",
      etiqueta: "Cargo",
      valor: cargo?.nombre_cargo ?? valores.cargo_id,
    });
  }
  if (valores.proceso_id) {
    const proceso = opciones.procesos.find((p) => String(p.proceso_id) === valores.proceso_id);
    chips.push({
      clave: "proceso_id",
      etiqueta: "Proceso",
      valor: proceso?.nombre ?? valores.proceso_id,
    });
  }
  if (muestraProyecto && valores.proyecto) {
    chips.push({ clave: "proyecto", etiqueta: "Proyecto", valor: valores.proyecto });
  }

  async function guardar(evento: FormEvent, datos: DatosTrabajador) {
    evento.preventDefault();
    if (!editando) {
      return;
    }
    setErroresApi(null);
    setError(null);
    setMensaje(null);

    const respuesta = await apiPut<PersonaCorporativa>(`/api/personal/${editando.persona_id}`, {
      correo: datos.correo.trim() || null,
      cargo_id: Number(datos.cargo_id),
      proceso_id: Number(datos.proceso_id),
      proyecto: datos.proyecto.trim() || null,
    });

    if (!respuesta.success) {
      setErroresApi(respuesta.errors);
      setError(respuesta.message || "No fue posible guardar el trabajador.");
      return;
    }

    setEditando(null);
    setMensaje(respuesta.message || "Trabajador actualizado.");
    void cargar(pagina);
  }

  return (
    <>
      <PageHeader
        titulo="Personal corporativo"
        descripcion="Consulta del maestro meridian_personal. En Editar se asigna el proceso HSEQ (y proyecto si aplica); ese contexto se suma a los procesos de la matriz. No se dan de alta trabajadores aquí."
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={valores.buscar}
            onChange={(e) => setFiltro("buscar", e.target.value)}
            placeholder="Documento, nombre o correo"
          />
        </Field>
        <Field etiqueta="Estado laboral">
          <select
            className={inputClass}
            value={valores.estado}
            onChange={(e) => setFiltro("estado", e.target.value)}
          >
            <option value="">Todos</option>
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
        </Field>
        <Field etiqueta="Cargo">
          <select
            className={inputClass}
            value={valores.cargo_id}
            onChange={(e) => setFiltro("cargo_id", e.target.value)}
          >
            <option value="">Todos</option>
            {opciones.cargos.map((c) => (
              <option key={c.cargo_id} value={c.cargo_id}>
                {c.nombre_cargo}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Proceso">
          <select
            className={inputClass}
            value={valores.proceso_id}
            onChange={(e) => setFiltro("proceso_id", e.target.value)}
          >
            <option value="">Todos</option>
            {opciones.procesos.map((p) => (
              <option key={p.proceso_id} value={p.proceso_id}>
                {p.nombre}
              </option>
            ))}
          </select>
        </Field>
        {muestraProyecto ? (
          <Field etiqueta="Proyecto">
            <select
              className={inputClass}
              value={valores.proyecto}
              onChange={(e) => setFiltro("proyecto", e.target.value)}
            >
              <option value="">Todos</option>
              {opciones.proyectos.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </select>
          </Field>
        ) : null}
      </Filters>

      <FiltrosActivos
        chips={chips}
        onQuitar={(clave) => setFiltro(clave, clave === "estado" ? "Activo" : "")}
        onLimpiar={limpiar}
      />

      {cargando ? (
        <ListaCargando />
      ) : (
        <Table
          columnas={[
            { clave: "doc", etiqueta: "Documento" },
            { clave: "nombre", etiqueta: "Nombre" },
            { clave: "cargo", etiqueta: "Cargo" },
            { clave: "proceso", etiqueta: "Proceso" },
            { clave: "proyecto", etiqueta: "Proyecto" },
            { clave: "estado", etiqueta: "Estado" },
            { clave: "ingreso", etiqueta: "Fecha de ingreso" },
            { clave: "acciones", etiqueta: "" },
          ]}
          filas={items.map((item) => [
            item.numero_documento,
            item.nombre_completo,
            item.cargo ?? "—",
            etiquetaProcesos(item),
            item.proyecto ?? "—",
            <Badge key="e" tono={item.estado === "Activo" ? "ok" : "aviso"}>
              {item.estado}
            </Badge>,
            item.contrato_fecha_inicio ?? "—",
            <div key="a" className="flex justify-end gap-1">
              {puede("personal.editar") ? (
                <Button
                  type="button"
                  variante="secondary"
                  className="px-2.5"
                  onClick={() => {
                    setEditando(item);
                    setErroresApi(null);
                  }}
                >
                  <Pencil className="h-4 w-4" aria-hidden />
                  Editar
                </Button>
              ) : null}
              <Link
                href={`/personal/detalle?id=${item.persona_id}`}
                className="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-hseq-700 hover:bg-hseq-50"
              >
                <IdCard className="h-4 w-4" aria-hidden />
                Ver perfil
              </Link>
            </div>,
          ])}
        />
      )}
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />

      <Modal
        abierto={editando !== null}
        titulo="Editar trabajador"
        onCerrar={() => {
          setEditando(null);
          setErroresApi(null);
        }}
      >
        {editando ? (
          <FormularioTrabajador
            inicial={editando}
            cargos={opciones.cargos}
            procesos={opciones.procesos}
            proyectos={opciones.proyectos}
            tiposDocumento={tiposDocumento}
            erroresApi={erroresApi}
            onCancelar={() => {
              setEditando(null);
              setErroresApi(null);
            }}
            onGuardar={(e, datos) => void guardar(e, datos)}
          />
        ) : null}
      </Modal>
    </>
  );
}
