"use client";

import { FormEvent, useEffect, useState } from "react";
import {
  FormularioCumplimiento,
  type DatosCumplimiento,
} from "@/app/(app)/cumplimientos/formulario";
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
import { apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type { Asignacion, Cumplimiento } from "@/lib/tipos";

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function etiquetaResultado(valor: string | null): string {
  if (valor === "APROBADO") return "Aprobado";
  if (valor === "ASISTIO") return "Asistió (borrador)";
  if (valor === "TARDE") return "Tarde (borrador)";
  return valor || "—";
}

export default function Page() {
  return (
    <RequierePermiso permiso="cumplimientos.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [items, setItems] = useState<Cumplimiento[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [buscar, setBuscar] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [editando, setEditando] = useState<Cumplimiento | null>(null);
  const [asignacionAlta, setAsignacionAlta] = useState<Asignacion | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [buscarAsig, setBuscarAsig] = useState("");
  const [candidatas, setCandidatas] = useState<Asignacion[]>([]);

  async function cargar(paginaActual = 1) {
    const respuesta = await apiGet<ListaPaginada<Cumplimiento>>(
      withQuery("/api/cumplimientos", {
        page: paginaActual,
        per_page: 15,
        buscar: buscar.trim() || undefined,
      }),
    );
    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible cargar los cumplimientos.");
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

  useEffect(() => {
    if (!abierto || editando) {
      return;
    }
    const id = window.setTimeout(() => {
      void (async () => {
        const r = await apiGet<ListaPaginada<Asignacion>>(
          withQuery("/api/asignaciones", {
            page: 1,
            per_page: 8,
            buscar: buscarAsig.trim() || undefined,
          }),
        );
        const lista = r.success && r.data ? r.data.items : [];
        setCandidatas(
          lista.filter(
            (a) =>
              a.cumplimiento_sesion_id &&
              a.cumplimiento_resultado !== "APROBADO",
          ),
        );
      })();
    }, 300);
    return () => window.clearTimeout(id);
  }, [abierto, editando, buscarAsig]);

  async function guardar(evento: FormEvent, datos: DatosCumplimiento) {
    evento.preventDefault();
    setError(null);
    setEnviando(true);

    if (editando) {
      const respuesta = await apiPut<Cumplimiento>(`/api/cumplimientos/${editando.cumplimiento_id}`, {
        fecha_realizacion: datos.fecha_realizacion,
        resultado: datos.resultado,
        horas_efectivas: Number(datos.horas_efectivas),
        observaciones: datos.observaciones.trim() || null,
      });
      setEnviando(false);
      if (!respuesta.success) {
        setError(respuesta.message || "No fue posible actualizar el cumplimiento.");
        return;
      }
      setMensaje(respuesta.message);
      setAbierto(false);
      setEditando(null);
      await cargar(pagina);
      return;
    }

    if (!asignacionAlta?.cumplimiento_sesion_id) {
      setEnviando(false);
      setError("Seleccione una asignación con asistencia registrada.");
      return;
    }

    const respuesta = await apiPost<Cumplimiento>("/api/cumplimientos", {
      asignacion_id: asignacionAlta.asignacion_id,
      sesion_id: asignacionAlta.cumplimiento_sesion_id,
      fecha_realizacion: datos.fecha_realizacion,
      resultado: datos.resultado,
      horas_efectivas: Number(datos.horas_efectivas),
      observaciones: datos.observaciones.trim() || null,
    });
    setEnviando(false);
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible registrar el cumplimiento.");
      return;
    }
    setMensaje(respuesta.message);
    setAbierto(false);
    setAsignacionAlta(null);
    await cargar(1);
  }

  return (
    <>
      <PageHeader
        titulo="Cumplimientos"
        descripcion="Complete el resultado y las horas efectivas. El vencimiento se calcula con la periodicidad de la matriz."
        acciones={
          puede("cumplimientos.crear") ? (
            <Button
              type="button"
              onClick={() => {
                setEditando(null);
                setAsignacionAlta(null);
                setAbierto(true);
              }}
            >
              Registrar cumplimiento
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
            placeholder="Trabajador, documento o capacitación"
          />
        </Field>
      </Filters>

      <Table
        columnas={[
          { clave: "doc", etiqueta: "Documento" },
          { clave: "persona", etiqueta: "Trabajador" },
          { clave: "cap", etiqueta: "Capacitación" },
          { clave: "fecha", etiqueta: "Realización" },
          { clave: "resultado", etiqueta: "Resultado" },
          { clave: "horas", etiqueta: "Horas" },
          { clave: "vence", etiqueta: "Vencimiento" },
          { clave: "acciones", etiqueta: "" },
        ]}
        filas={items.map((item) => [
          item.numero_documento ?? "—",
          item.persona_nombre ?? `Persona ${item.persona_id_ext}`,
          item.capacitacion_codigo
            ? `${item.capacitacion_codigo} — ${item.capacitacion_nombre}`
            : (item.capacitacion_nombre ?? "—"),
          formatoFecha(item.fecha_realizacion),
          <Badge key="r" tono={item.resultado === "APROBADO" ? "ok" : "aviso"}>
            {etiquetaResultado(item.resultado)}
          </Badge>,
          item.horas_efectivas ?? "—",
          item.fecha_vencimiento ? formatoFecha(item.fecha_vencimiento) : "Sin vencimiento",
          <span key="a" className="flex flex-wrap gap-2">
            {puede("cumplimientos.crear") && item.resultado !== "APROBADO" && item.sesion_id ? (
              <Button
                type="button"
                variante="ghost"
                onClick={() => {
                  setEditando(null);
                  setAsignacionAlta({
                    asignacion_id: item.asignacion_id,
                    persona_id_ext: item.persona_id_ext ?? 0,
                    persona_nombre: item.persona_nombre,
                    numero_documento: item.numero_documento,
                    contrato_id_ext: null,
                    capacitacion_id: item.capacitacion_id ?? 0,
                    capacitacion_codigo: item.capacitacion_codigo ?? "",
                    capacitacion_nombre: item.capacitacion_nombre ?? "",
                    fecha_asignacion: "",
                    fecha_limite_cumplimiento: "",
                    origen: "",
                    periodicidad_nombre: null,
                    obligatoria: null,
                    cargo_id_ext: null,
                    ambito: null,
                    proyecto: null,
                    estado_calculado: "",
                    tiene_cumplimiento: true,
                    cumplimiento_id: item.cumplimiento_id,
                    cumplimiento_sesion_id: item.sesion_id,
                    cumplimiento_resultado: item.resultado,
                    fecha_realizacion: item.fecha_realizacion,
                    horas_efectivas: item.horas_efectivas,
                    fecha_vencimiento: item.fecha_vencimiento,
                    dias_restantes: null,
                    etiqueta_dias: null,
                  });
                  setAbierto(true);
                }}
              >
                Completar
              </Button>
            ) : null}
            {puede("cumplimientos.editar") && item.resultado === "APROBADO" ? (
              <Button
                type="button"
                variante="ghost"
                onClick={() => {
                  setAsignacionAlta(null);
                  setEditando(item);
                  setAbierto(true);
                }}
              >
                Editar
              </Button>
            ) : null}
          </span>,
        ])}
        vacio="No hay cumplimientos registrados."
      />
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />

      <Modal
        abierto={abierto}
        titulo={editando ? "Editar cumplimiento" : "Registrar cumplimiento"}
        onCerrar={() => {
          setAbierto(false);
          setEditando(null);
          setAsignacionAlta(null);
        }}
      >
        {!editando && !asignacionAlta ? (
          <div className="space-y-3">
            <Field etiqueta="Trabajador o documento">
              <input
                className={inputClass}
                value={buscarAsig}
                onChange={(e) => setBuscarAsig(e.target.value)}
                placeholder="Buscar asignación con asistencia"
              />
            </Field>
            <ul className="max-h-64 space-y-1 overflow-y-auto">
              {candidatas.map((a) => (
                <li key={a.asignacion_id}>
                  <button
                    type="button"
                    className="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-hseq-50"
                    onClick={() => setAsignacionAlta(a)}
                  >
                    <span className="font-medium">{a.persona_nombre}</span>
                    {a.numero_documento ? (
                      <span className="ml-1 text-slate-500">{a.numero_documento}</span>
                    ) : null}
                    <span className="block text-xs text-slate-500">
                      {a.capacitacion_codigo} — {a.capacitacion_nombre}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
            {candidatas.length === 0 ? (
              <p className="text-sm text-slate-500">
                Solo aparecen asignaciones con asistencia ASISTIÓ o TARDE pendientes de aprobar.
              </p>
            ) : null}
          </div>
        ) : (
          <FormularioCumplimiento
            key={editando ? `e-${editando.cumplimiento_id}` : `a-${asignacionAlta?.asignacion_id}`}
            asignacionId={editando?.asignacion_id ?? asignacionAlta?.asignacion_id ?? 0}
            sesionId={editando?.sesion_id ?? asignacionAlta?.cumplimiento_sesion_id ?? 0}
            fechaDefault={
              (editando?.fecha_realizacion ?? asignacionAlta?.fecha_realizacion ?? "").slice(0, 10)
            }
            enviando={enviando}
            modoEdicion={Boolean(editando)}
            onCancelar={() => {
              setAbierto(false);
              setEditando(null);
              setAsignacionAlta(null);
            }}
            onSubmit={guardar}
          />
        )}
      </Modal>
    </>
  );
}
