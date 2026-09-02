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
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type { Capacitacion, CargoCorporativo, FilaMatriz, ItemCatalogo } from "@/lib/tipos";
import { FormularioMatriz, type DatosMatriz } from "./formulario";
import { FormularioMatrizMasiva, type DatosMatrizMasiva } from "./formulario-masivo";
import { Link2, Pencil, Plus, UserMinus, WandSparkles } from "lucide-react";

type ResultadoMasivo = {
  creadas: number;
  omitidas: number;
  items: FilaMatriz[];
  omitidas_detalle: { cargo_id_ext: number; motivo: string }[];
};

type ResultadoMotor = {
  creadas: number;
  omitidas: number;
};

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
  const [cargoId, setCargoId] = useState("");
  const [procesoId, setProcesoId] = useState("");
  const [proyecto, setProyecto] = useState("");
  const [estado, setEstado] = useState("activas");
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [masivoAbierto, setMasivoAbierto] = useState(false);
  const [editando, setEditando] = useState<FilaMatriz | null>(null);
  const [capacitaciones, setCapacitaciones] = useState<Capacitacion[]>([]);
  const [cargos, setCargos] = useState<CargoCorporativo[]>([]);
  const [areas, setAreas] = useState<ItemCatalogo[]>([]);
  const [procesos, setProcesos] = useState<ItemCatalogo[]>([]);
  const [periodicidades, setPeriodicidades] = useState<ItemCatalogo[]>([]);
  const [cargando, setCargando] = useState(true);

  async function cargar(paginaActual = pagina) {
    setCargando(true);
    const respuesta = await apiGet<ListaPaginada<FilaMatriz>>(
      withQuery("/api/matriz", {
        page: paginaActual,
        per_page: 15,
        capacitacion_id: capacitacionId || undefined,
        cargo_id_ext: cargoId || undefined,
        proceso_id: procesoId || undefined,
        proyecto: proyecto.trim() || undefined,
        estado: estado || undefined,
      }),
    );
    setCargando(false);

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar la matriz.");
      return;
    }

    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  useDebouncedCallback(() => {
    void cargar(1);
  }, [capacitacionId, cargoId, procesoId, proyecto, estado]);

  useEffect(() => {
    void (async () => {
      const [caps, car, ar, pr, pe] = await Promise.all([
        apiGet<ListaPaginada<Capacitacion>>(withQuery("/api/capacitaciones", { per_page: 100, estado: "ACTIVA" })),
        apiGet<CargoCorporativo[]>("/api/personal/cargos"),
        apiGet<{ items: ItemCatalogo[] }>("/api/catalogs/areas?activos=1"),
        apiGet<{ items: ItemCatalogo[] }>("/api/catalogs/procesos?activos=1"),
        apiGet<{ items: ItemCatalogo[] }>("/api/catalogs/periodicidades?activos=1"),
      ]);
      setCapacitaciones(caps.data?.items ?? []);
      setCargos(car.data ?? []);
      setAreas(ar.data?.items ?? []);
      setProcesos(pr.data?.items ?? []);
      setPeriodicidades(pe.data?.items ?? []);
    })();
  }, []);

  function limpiarFiltros() {
    setCapacitacionId("");
    setCargoId("");
    setProcesoId("");
    setProyecto("");
    setEstado("activas");
  }

  const chips: ChipFiltro[] = [];
  if (cargoId) {
    const cargo = cargos.find((c) => String(c.cargo_id) === cargoId);
    chips.push({ clave: "cargo_id", etiqueta: "Cargo", valor: cargo?.nombre_cargo ?? cargoId });
  }
  if (procesoId) {
    const proceso = procesos.find((p) => String(p.proceso_id) === procesoId);
    chips.push({
      clave: "proceso_id",
      etiqueta: "Proceso",
      valor: proceso ? String(proceso.nombre) : procesoId,
    });
  }
  if (proyecto.trim()) {
    chips.push({ clave: "proyecto", etiqueta: "Proyecto", valor: proyecto.trim() });
  }
  if (capacitacionId) {
    const cap = capacitaciones.find((c) => String(c.capacitacion_id) === capacitacionId);
    chips.push({
      clave: "capacitacion_id",
      etiqueta: "Capacitación",
      valor: cap ? `${cap.codigo} — ${cap.nombre}` : capacitacionId,
    });
  }
  if (estado !== "activas") {
    chips.push({
      clave: "estado",
      etiqueta: "Estado",
      valor: estado === "todos" ? "Todas" : estado === "inactivas" ? "Inactivas" : estado,
    });
  }

  function quitarChip(clave: string) {
    if (clave === "cargo_id") setCargoId("");
    if (clave === "proceso_id") setProcesoId("");
    if (clave === "proyecto") setProyecto("");
    if (clave === "capacitacion_id") setCapacitacionId("");
    if (clave === "estado") setEstado("activas");
  }

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
    setError(null);
    setAbierto(false);
    setEditando(null);
    await cargar();
  }

  async function guardarMasivo(evento: FormEvent, datos: DatosMatrizMasiva) {
    evento.preventDefault();
    const n = (valor: string) => (valor === "" ? null : Number(valor));
    const respuesta = await apiPost<ResultadoMasivo>("/api/matriz/asociar-masivo", {
      capacitacion_id: Number(datos.capacitacion_id),
      cargo_ids_ext: datos.cargo_ids_ext.map((id) => Number(id)),
      proceso_id: n(datos.proceso_id),
      proyecto: datos.proyecto.trim() || null,
      periodicidad_id: n(datos.periodicidad_id),
      obligatoria: datos.obligatoria ? 1 : 0,
    });

    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo asociar.");
      return;
    }

    setMensaje(respuesta.message);
    setError(null);
    setMasivoAbierto(false);
    await cargar(1);
  }

  async function inactivar(item: FilaMatriz) {
    if (!confirm("¿Está seguro de inactivar esta regla de aplicabilidad?")) {
      return;
    }
    const r = await apiDelete(`/api/matriz/${item.matriz_aplicabilidad_id}`);
    if (!r.success) {
      setError(r.message || "No se pudo inactivar.");
      return;
    }
    setMensaje(r.message);
    setError(null);
    await cargar();
  }

  async function reactivar(item: FilaMatriz) {
    const r = await apiPut<FilaMatriz>(`/api/matriz/${item.matriz_aplicabilidad_id}`, { activa: 1 });
    if (!r.success) {
      setError(r.message || "No se pudo reactivar.");
      return;
    }
    setMensaje(r.message || "Registro reactivado");
    setError(null);
    await cargar();
  }

  async function generarAutomaticas() {
    if (!confirm("¿Generar asignaciones automáticas a partir de las reglas activas de la matriz?")) {
      return;
    }
    const r = await apiPost<ResultadoMotor>("/api/asignaciones/generar-automaticas", {});
    if (!r.success) {
      setError(r.message || "No se pudieron generar las asignaciones.");
      return;
    }
    setMensaje(r.message);
    setError(null);
  }

  return (
    <>
      <PageHeader
        titulo="Matriz de aplicabilidad"
        descripcion="Define qué capacitaciones aplican a cada combinación de cargo, proceso y proyecto. El motor de asignación automática consulta estas reglas activas."
        acciones={
          <div className="flex flex-wrap gap-2">
            {puede("asignaciones.crear") ? (
              <Button type="button" variante="secondary" onClick={() => void generarAutomaticas()}>
                <WandSparkles className="h-4 w-4" aria-hidden />
                Generar asignaciones automáticas
              </Button>
            ) : null}
            {puede("matriz.crear") ? (
              <>
                  <Button
                    type="button"
                    variante="secondary"
                    onClick={() => {
                      setEditando(null);
                      setAbierto(true);
                    }}
                  >
                    <Plus className="h-4 w-4" aria-hidden />
                    Nueva fila
                  </Button>
                  <Button type="button" onClick={() => setMasivoAbierto(true)}>
                    <Link2 className="h-4 w-4" aria-hidden />
                    Asociar capacitación
                  </Button>
              </>
            ) : null}
          </div>
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <Filters>
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
        <Field etiqueta="Proceso">
          <select className={inputClass} value={procesoId} onChange={(e) => setProcesoId(e.target.value)}>
            <option value="">Todos</option>
            {procesos.map((p) => (
              <option key={String(p.proceso_id)} value={String(p.proceso_id)}>
                {String(p.nombre)}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Proyecto">
          <input
            className={inputClass}
            value={proyecto}
            onChange={(e) => setProyecto(e.target.value)}
            placeholder="Texto del proyecto"
          />
        </Field>
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
        <Field etiqueta="Estado">
          <select className={inputClass} value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="todos">Todas</option>
            <option value="activas">Activas</option>
            <option value="inactivas">Inactivas</option>
          </select>
        </Field>
      </Filters>

      <FiltrosActivos chips={chips} onQuitar={quitarChip} onLimpiar={limpiarFiltros} />

      {cargando ? (
        <ListaCargando />
      ) : (
        <Table
          columnas={[
            { clave: "cargo", etiqueta: "Cargo" },
            { clave: "proceso", etiqueta: "Proceso" },
            { clave: "proyecto", etiqueta: "Proyecto" },
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "periodicidad", etiqueta: "Periodicidad" },
            { clave: "obligatoria", etiqueta: "Obligatoria" },
            { clave: "estado", etiqueta: "Estado" },
            { clave: "acciones", etiqueta: "" },
          ]}
          filas={items.map((item) => [
            item.cargo_nombre ?? (item.cargo_id_ext ? `Cargo ${item.cargo_id_ext}` : "Cualquier cargo"),
            item.proceso_nombre ?? "—",
            item.proyecto ?? "—",
            <div key="c">
              <p className="font-medium">{item.capacitacion_codigo}</p>
              <p className="text-xs text-slate-500">{item.capacitacion_nombre}</p>
            </div>,
            item.periodicidad_nombre ?? "—",
            <Badge key="o" tono={item.obligatoria ? "alto" : "neutral"}>
              {item.obligatoria ? "Sí" : "No"}
            </Badge>,
            <Badge key="e" tono={item.activa ? "ok" : "neutral"}>
              {item.activa ? "Activa" : "Inactiva"}
            </Badge>,
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
                  <Pencil className="h-4 w-4" aria-hidden />
                  Editar
                </Button>
              ) : null}
              {item.activa
                ? puede("matriz.eliminar")
                  ? (
                    <Button type="button" variante="ghost" onClick={() => void inactivar(item)}>
                      <UserMinus className="h-4 w-4" aria-hidden />
                      Inactivar
                    </Button>
                  )
                  : null
                : puede("matriz.editar")
                  ? (
                    <Button type="button" variante="ghost" onClick={() => void reactivar(item)}>
                      Reactivar
                    </Button>
                  )
                  : null}
            </div>,
          ])}
        />
      )}
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

      <Modal abierto={masivoAbierto} titulo="Asociar capacitación a cargos" onCerrar={() => setMasivoAbierto(false)}>
        <FormularioMatrizMasiva
          key={masivoAbierto ? "masivo-abierto" : "masivo"}
          capacitaciones={capacitaciones}
          cargos={cargos}
          procesos={procesos}
          periodicidades={periodicidades}
          onCancelar={() => setMasivoAbierto(false)}
          onGuardar={guardarMasivo}
        />
      </Modal>
    </>
  );
}
