"use client";

import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import {
  FormularioAsignacion,
  type DatosAsignacion,
} from "@/app/(app)/asignaciones/formulario";
import {
  FormularioAsignacionMasiva,
  type DatosAsignacionMasiva,
} from "@/app/(app)/asignaciones/formulario-masivo";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type { Asignacion, Capacitacion, ProximasAsignaciones } from "@/lib/tipos";

const ETIQUETAS_ESTADO: Record<string, string> = {
  PENDIENTE: "Pendiente",
  PENDIENTE_PROXIMA_A_VENCER: "Próxima a vencer",
  PENDIENTE_VENCIDA: "Pendiente vencida",
  COMPLETADA: "Completada",
  PROXIMA_A_VENCER: "Vigencia próxima a vencer",
  VENCIDA: "Vigencia vencida",
};

function tonoEstado(estado: string) {
  if (estado === "PENDIENTE_VENCIDA" || estado === "VENCIDA") return "alto" as const;
  if (estado === "PENDIENTE_PROXIMA_A_VENCER" || estado === "PROXIMA_A_VENCER") return "aviso" as const;
  if (estado === "COMPLETADA") return "ok" as const;
  return "neutral" as const;
}

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function etiquetaOrigen(origen: string): string {
  if (origen === "AUTOMATICA") return "Automática";
  if (origen === "MANUAL") return "Manual";
  return origen;
}

type PersonaHistorial = {
  id: number;
  nombre: string;
  documento: string | null;
};

function leerHistorialUrl(): PersonaHistorial | null {
  if (typeof window === "undefined") {
    return null;
  }

  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get("persona_id") || 0);
  if (!Number.isFinite(id) || id < 1) {
    return null;
  }

  const nombre = (params.get("nombre") ?? "").trim();
  const documento = (params.get("documento") ?? "").trim();

  return {
    id,
    nombre: nombre || `Persona ${id}`,
    documento: documento || null,
  };
}

function rutaHistorial(persona: PersonaHistorial): string {
  return withQuery("/asignaciones", {
    persona_id: persona.id,
    nombre: persona.nombre,
    documento: persona.documento,
  });
}

export default function AsignacionesPage() {
  return (
    <RequierePermiso permiso="asignaciones.ver">
      <Contenido />
    </RequierePermiso>
  );
}

type ResultadoMasivo = {
  seleccionados: number;
  creadas: number;
  omitidas: number;
  errores: number;
};

function Contenido() {
  const { puede } = useAuth();
  const router = useRouter();
  const [items, setItems] = useState<Asignacion[]>([]);
  const [proximas, setProximas] = useState<ProximasAsignaciones>({ total: 0, items: [] });
  const [capacitaciones, setCapacitaciones] = useState<Capacitacion[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [buscar, setBuscar] = useState("");
  const [capacitacionId, setCapacitacionId] = useState("");
  const [estado, setEstado] = useState("");
  const [origen, setOrigen] = useState("");
  const [personaHistorial, setPersonaHistorial] = useState<PersonaHistorial | null>(null);
  const [historialListo, setHistorialListo] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [masivoAbierto, setMasivoAbierto] = useState(false);
  const [editando, setEditando] = useState<Asignacion | null>(null);

  async function cargarListado(paginaActual = 1) {
    const respuesta = await apiGet<ListaPaginada<Asignacion>>(
      withQuery("/api/asignaciones", {
        page: paginaActual,
        per_page: 15,
        buscar: personaHistorial ? undefined : buscar.trim() || undefined,
        persona_id: personaHistorial?.id,
        capacitacion_id: capacitacionId || undefined,
        estado: estado || undefined,
        origen: origen || undefined,
      }),
    );

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar las asignaciones.");
      return;
    }

    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  async function cargarProximas() {
    const respuesta = await apiGet<ProximasAsignaciones>("/api/asignaciones/proximas");
    if (respuesta.success && respuesta.data) {
      setProximas(respuesta.data);
    }
  }

  async function refrescar(paginaActual = pagina) {
    await Promise.all([cargarListado(paginaActual), cargarProximas()]);
  }

  useEffect(() => {
    setPersonaHistorial(leerHistorialUrl());
    setHistorialListo(true);
  }, []);

  useEffect(() => {
    if (!historialListo) {
      return;
    }
    const id = window.setTimeout(() => {
      void cargarListado(1);
    }, 300);
    return () => window.clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [buscar, capacitacionId, estado, origen, personaHistorial, historialListo]);

  useEffect(() => {
    void cargarProximas();
    void (async () => {
      const caps = await apiGet<ListaPaginada<Capacitacion>>(
        withQuery("/api/capacitaciones", { per_page: 100, estado: "ACTIVA" }),
      );
      setCapacitaciones(caps.data?.items ?? []);
    })();
  }, []);

  async function guardar(evento: FormEvent, datos: DatosAsignacion) {
    evento.preventDefault();
    setError(null);

    if (editando) {
      const respuesta = await apiPut<Asignacion>(`/api/asignaciones/${editando.asignacion_id}`, {
        fecha_limite_cumplimiento: datos.fecha_limite_cumplimiento,
      });
      if (!respuesta.success) {
        setError(respuesta.message || "No se pudo actualizar la fecha.");
        return;
      }
      setMensaje(respuesta.message);
    } else {
      if (!datos.persona_id_ext || !datos.capacitacion_id) {
        setError("Seleccione trabajador y capacitación.");
        return;
      }
      const respuesta = await apiPost<Asignacion>("/api/asignaciones", {
        persona_id_ext: Number(datos.persona_id_ext),
        capacitacion_id: Number(datos.capacitacion_id),
        fecha_limite_cumplimiento: datos.fecha_limite_cumplimiento,
        fecha_asignacion: datos.fecha_asignacion || undefined,
      });
      if (!respuesta.success) {
        setError(respuesta.message || "No se pudo asignar la capacitación.");
        return;
      }
      setMensaje(respuesta.message);
    }

    setAbierto(false);
    setEditando(null);
    await refrescar(1);
  }

  async function guardarMasivo(evento: FormEvent, datos: DatosAsignacionMasiva) {
    evento.preventDefault();
    const n = datos.persona_ids_ext.length;
    if (
      !confirm(
        `¿Desea asignar esta capacitación a los ${n} trabajador${n === 1 ? "" : "es"} seleccionado${n === 1 ? "" : "s"}?`,
      )
    ) {
      return;
    }

    const respuesta = await apiPost<ResultadoMasivo>("/api/asignaciones/masivo", {
      capacitacion_id: Number(datos.capacitacion_id),
      persona_ids_ext: datos.persona_ids_ext.map(Number),
      fecha_limite_cumplimiento: datos.fecha_limite_cumplimiento || undefined,
    });

    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo completar la asignación masiva.");
      return;
    }

    setMensaje(respuesta.message);
    setError(null);
    setMasivoAbierto(false);
    await refrescar(1);
  }

  async function eliminar(item: Asignacion) {
    if (!confirm("¿Eliminar esta asignación?")) {
      return;
    }
    const respuesta = await apiDelete(`/api/asignaciones/${item.asignacion_id}`);
    if (!respuesta.success) {
      setError(respuesta.message || "No se pudo eliminar.");
      return;
    }
    setMensaje(respuesta.message);
    await refrescar();
  }

  function verHistorial(item: Asignacion) {
    const filtro: PersonaHistorial = {
      id: item.persona_id_ext,
      nombre: item.persona_nombre ?? `Persona ${item.persona_id_ext}`,
      documento: item.numero_documento,
    };
    setPersonaHistorial(filtro);
    setBuscar("");
    router.replace(rutaHistorial(filtro));
  }

  function quitarFiltroHistorial() {
    setPersonaHistorial(null);
    router.replace("/asignaciones");
  }

  return (
    <>
      <PageHeader
        titulo="Asignaciones"
        descripcion="Asigne capacitaciones a trabajadores y consulte el plazo de cumplimiento. La alerta de 10 días se calcula sola."
        acciones={
          puede("asignaciones.crear") ? (
            <span className="flex flex-wrap gap-2">
              <Button
                type="button"
                variante="secondary"
                onClick={() => setMasivoAbierto(true)}
              >
                Asignación masiva
              </Button>
              <Button
                type="button"
                onClick={() => {
                  setEditando(null);
                  setAbierto(true);
                }}
              >
                Asignar capacitación
              </Button>
            </span>
          ) : null
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      {personaHistorial ? (
        <Alert tono="aviso">
          Historial de {personaHistorial.nombre}
          {personaHistorial.documento ? ` · ${personaHistorial.documento}` : ""}.{" "}
          <button
            type="button"
            className="font-medium underline"
            onClick={quitarFiltroHistorial}
          >
            Quitar filtro
          </button>
        </Alert>
      ) : null}

      <Card className="mb-6">
        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="text-sm font-semibold text-hseq-900">Próximas a vencer</h2>
          <p className="text-sm text-slate-600">
            Capacitaciones próximas a vencer:{" "}
            <span className="font-semibold text-hseq-900">{proximas.total}</span>
          </p>
        </div>
        {proximas.total === 0 ? (
          <p className="text-sm text-slate-500">
            No hay capacitaciones próximas a vencer en los próximos 10 días.
          </p>
        ) : (
          <Table
            columnas={[
              { clave: "persona", etiqueta: "Trabajador" },
              { clave: "cap", etiqueta: "Capacitación" },
              { clave: "fecha", etiqueta: "Fecha límite" },
              { clave: "dias", etiqueta: "Días restantes" },
            ]}
            filas={proximas.items.map((item) => [
              <span key="p">
                {item.persona_nombre ?? `Persona ${item.persona_id_ext}`}
                {item.numero_documento ? (
                  <span className="ml-1 text-xs text-slate-500">{item.numero_documento}</span>
                ) : null}
              </span>,
              <span key="c">
                {item.capacitacion_nombre}
                <span className="ml-1 text-xs text-slate-500">{item.capacitacion_codigo}</span>
              </span>,
              formatoFecha(item.fecha_limite_cumplimiento),
              <Badge key="d" tono="aviso">
                {item.etiqueta_dias ?? "Próxima a vencer"}
              </Badge>,
            ])}
          />
        )}
      </Card>

      <Filters>
        <Field etiqueta="Trabajador">
          <input
            className={inputClass}
            value={buscar}
            onChange={(e) => {
              if (personaHistorial !== null) {
                quitarFiltroHistorial();
              }
              setBuscar(e.target.value);
            }}
            placeholder="Nombre o documento"
            disabled={personaHistorial !== null}
          />
        </Field>
        <Field etiqueta="Capacitación">
          <select
            className={inputClass}
            value={capacitacionId}
            onChange={(e) => setCapacitacionId(e.target.value)}
          >
            <option value="">Todas</option>
            {capacitaciones.map((cap) => (
              <option key={cap.capacitacion_id} value={cap.capacitacion_id}>
                {cap.codigo} — {cap.nombre}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Origen">
          <select className={inputClass} value={origen} onChange={(e) => setOrigen(e.target.value)}>
            <option value="">Todos</option>
            <option value="AUTOMATICA">Automática</option>
            <option value="MANUAL">Manual</option>
          </select>
        </Field>
        <Field etiqueta="Estado">
          <select className={inputClass} value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todos</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="PENDIENTE_PROXIMA_A_VENCER">Próxima a vencer</option>
            <option value="PENDIENTE_VENCIDA">Pendiente vencida</option>
            <option value="COMPLETADA">Completada</option>
            <option value="PROXIMA_A_VENCER">Vigencia próxima a vencer</option>
            <option value="VENCIDA">Vigencia vencida</option>
          </select>
        </Field>
      </Filters>

      <Table
        columnas={[
          { clave: "documento", etiqueta: "Documento" },
          { clave: "persona", etiqueta: "Trabajador" },
          { clave: "cap", etiqueta: "Capacitación" },
          { clave: "origen", etiqueta: "Origen" },
          { clave: "periodicidad", etiqueta: "Periodicidad" },
          { clave: "obligatoria", etiqueta: "Obligatoria" },
          { clave: "limite", etiqueta: "Fecha límite" },
          { clave: "estado", etiqueta: "Estado" },
          { clave: "dias", etiqueta: "Días" },
          { clave: "acciones", etiqueta: "" },
        ]}
        filas={items.map((item) => [
          item.numero_documento ?? "—",
          <button
            key="p"
            type="button"
            className="text-left font-medium text-hseq-800 underline-offset-2 hover:underline"
            onClick={() => verHistorial(item)}
          >
            {item.persona_nombre ?? `Persona ${item.persona_id_ext}`}
          </button>,
          `${item.capacitacion_codigo} — ${item.capacitacion_nombre}`,
          etiquetaOrigen(item.origen),
          item.periodicidad_nombre ?? "—",
          item.obligatoria === null ? "—" : item.obligatoria ? "Sí" : "No",
          formatoFecha(item.fecha_limite_cumplimiento),
          <Badge key="e" tono={tonoEstado(item.estado_calculado)}>
            {ETIQUETAS_ESTADO[item.estado_calculado] ?? item.estado_calculado}
          </Badge>,
          item.estado_calculado === "PENDIENTE_PROXIMA_A_VENCER" || item.estado_calculado === "PENDIENTE"
            ? item.etiqueta_dias ?? "—"
            : "—",
          <span key="a" className="flex flex-wrap gap-2">
            {puede("asignaciones.editar") ? (
              <Button
                type="button"
                variante="ghost"
                onClick={() => {
                  setEditando(item);
                  setAbierto(true);
                }}
              >
                Fecha
              </Button>
            ) : null}
            {puede("asignaciones.eliminar") && !item.tiene_cumplimiento ? (
              <Button type="button" variante="danger" onClick={() => void eliminar(item)}>
                Eliminar
              </Button>
            ) : null}
          </span>,
        ])}
        vacio="No hay asignaciones para los filtros seleccionados."
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargarListado(p)} />

      <Modal
        abierto={abierto}
        titulo={editando ? "Actualizar fecha límite" : "Asignar capacitación"}
        onCerrar={() => {
          setAbierto(false);
          setEditando(null);
        }}
      >
        <FormularioAsignacion
          key={editando ? String(editando.asignacion_id) : "nueva"}
          inicial={editando}
          capacitaciones={capacitaciones}
          soloFecha={Boolean(editando)}
          onSubmit={guardar}
          onCancelar={() => {
            setAbierto(false);
            setEditando(null);
          }}
        />
      </Modal>

      <Modal
        abierto={masivoAbierto}
        titulo="Asignación masiva"
        onCerrar={() => setMasivoAbierto(false)}
      >
        <FormularioAsignacionMasiva
          key={masivoAbierto ? "masivo-abierto" : "masivo-cerrado"}
          capacitaciones={capacitaciones}
          onCancelar={() => setMasivoAbierto(false)}
          onGuardar={guardarMasivo}
        />
      </Modal>
    </>
  );
}
