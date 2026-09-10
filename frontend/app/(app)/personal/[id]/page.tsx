"use client";

import { useEffect, useState, type ReactNode } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { ListaEvidencias } from "@/app/(app)/cumplimientos/evidencias";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { ListaCargando } from "@/components/ui/filtros-activos";
import { PageHeader } from "@/components/ui/page-header";
import { Table } from "@/components/ui/table";
import { ArrowLeft } from "lucide-react";
import { apiGet } from "@/lib/api";
import { humanizarNombreUnidad } from "@/lib/catalogos";
import type { Asignacion, Cumplimiento, PerfilTrabajador, PersonaCorporativa } from "@/lib/tipos";

function formatoFecha(valor: string | null | undefined): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function etiquetaProcesos(ficha: PersonaCorporativa): string {
  const nombres = (ficha.procesos ?? []).map((p) => p.nombre).filter(Boolean);
  return nombres.length > 0 ? nombres.join(", ") : "—";
}

function etiquetaCapacitacion(item: {
  capacitacion_codigo?: string | null;
  capacitacion_nombre?: string | null;
}): string {
  if (item.capacitacion_codigo && item.capacitacion_nombre) {
    return `${item.capacitacion_codigo} — ${item.capacitacion_nombre}`;
  }
  return item.capacitacion_nombre ?? item.capacitacion_codigo ?? "—";
}

function etiquetaAsistencia(valor: string): string {
  if (valor === "ASISTIO") return "Asistió";
  if (valor === "TARDE") return "Tarde";
  if (valor === "AUSENTE") return "Ausente";
  if (valor === "PENDIENTE") return "Pendiente";
  return valor || "—";
}

function etiquetaNota(item: Cumplimiento): string {
  if (item.nota_evaluacion == null) return "—";
  const n = item.nota_evaluacion.toFixed(2).replace(".", ",");
  if (item.evaluacion_aprobada === true) return `${n} · Aprobado`;
  if (item.evaluacion_aprobada === false) return `${n} · No aprobado`;
  return n;
}

function tonoEstado(estado: string): "alto" | "aviso" | "ok" | "neutral" {
  if (estado === "VENCIDA" || estado === "PENDIENTE_VENCIDA") return "alto";
  if (estado === "PROXIMA_A_VENCER" || estado === "PENDIENTE_PROXIMA_A_VENCER") return "aviso";
  if (estado === "COMPLETADA") return "ok";
  return "neutral";
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
  return (
    <div>
      <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{etiqueta}</dt>
      <dd className="mt-1 text-sm text-slate-800">{valor}</dd>
    </div>
  );
}

function Bloque({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <Card>
      <h2 className="mb-4 text-base font-semibold text-hseq-900">{titulo}</h2>
      {children}
    </Card>
  );
}

function filasAsignacion(items: Asignacion[]) {
  return items.map((item) => [
    etiquetaCapacitacion(item),
    item.origen,
    formatoFecha(item.fecha_asignacion),
    formatoFecha(item.fecha_limite_cumplimiento),
    <Badge key="e" tono={tonoEstado(item.estado_calculado)}>
      {item.estado_calculado}
    </Badge>,
  ]);
}

export default function PerfilPersonalPage() {
  return (
    <RequierePermiso permiso="personal.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const [perfil, setPerfil] = useState<PerfilTrabajador | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    if (!Number.isFinite(id) || id < 1) {
      setError("El trabajador no existe en la base corporativa.");
      setCargando(false);
      return;
    }

    void (async () => {
      setCargando(true);
      const respuesta = await apiGet<PerfilTrabajador>(`/api/personal/${id}/perfil`);
      setCargando(false);
      if (respuesta.cancelada) {
        return;
      }
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible consultar la información del trabajador.");
        setPerfil(null);
        return;
      }
      setPerfil(respuesta.data);
      setMensaje(respuesta.message || "Información del trabajador cargada correctamente.");
      setError(null);
    })();
  }, [id]);

  if (cargando) {
    return <ListaCargando />;
  }

  if (error && !perfil) {
    return (
      <>
        <PageHeader
          titulo="Perfil del trabajador"
          acciones={
            <Link
              href="/personal"
              prefetch={false}
              className="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-hseq-700 hover:bg-hseq-50"
            >
              <ArrowLeft className="h-4 w-4" aria-hidden />
              Volver al listado
            </Link>
          }
        />
        <Alert tono="error">{error}</Alert>
      </>
    );
  }

  if (!perfil) {
    return null;
  }

  const ficha = perfil.ficha;

  return (
    <div className="space-y-6">
      <PageHeader
        titulo={ficha.nombre_completo}
        descripcion="Hoja de vida corporativa y de capacitación. Los datos laborales se leen del maestro; el historial de HSEQ no se duplica."
        acciones={
          <Link
            href="/personal"
            prefetch={false}
            className="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-hseq-700 hover:bg-hseq-50"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden />
            Volver al listado
          </Link>
        }
      />

      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <Bloque titulo="Datos corporativos">
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Dato
            etiqueta="Tipo de documento"
            valor={ficha.tipo_documento_abreviatura ?? ficha.tipo_documento_nombre ?? "—"}
          />
          <Dato etiqueta="Documento" valor={ficha.numero_documento} />
          <Dato etiqueta="Nombre" valor={ficha.nombre_completo} />
          <Dato etiqueta="Cargo" valor={ficha.cargo ?? "—"} />
          <Dato etiqueta="Proceso" valor={etiquetaProcesos(ficha)} />
          <Dato etiqueta="Proyecto" valor={ficha.proyecto ?? "—"} />
          <Dato etiqueta="Estado laboral" valor={ficha.estado} />
          <Dato etiqueta="Fecha de ingreso" valor={formatoFecha(ficha.contrato_fecha_inicio)} />
          <Dato etiqueta="Celular" valor={ficha.celular ?? "—"} />
          <Dato etiqueta="Correo corporativo" valor={ficha.correo_corporativo ?? "—"} />
          <Dato etiqueta="Correo personal" valor={ficha.correo_personal ?? "—"} />
        </dl>
      </Bloque>

      <Bloque titulo="Capacitaciones aplicables">
        <Table
          vacio="No hay capacitaciones aplicables según la matriz."
          columnas={[
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "proceso", etiqueta: "Proceso" },
            { clave: "proyecto", etiqueta: "Proyecto" },
            { clave: "per", etiqueta: "Periodicidad" },
          ]}
          filas={perfil.aplicables.map((item) => [
            etiquetaCapacitacion(item),
            item.proceso_nombre ?? "—",
            item.proyecto ?? "—",
            humanizarNombreUnidad(item.periodicidad_nombre) || "—",
          ])}
        />
      </Bloque>

      <Bloque titulo="Pendientes">
        <Table
          vacio="No hay asignaciones pendientes."
          columnas={[
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "origen", etiqueta: "Origen" },
            { clave: "asig", etiqueta: "Asignación" },
            { clave: "limite", etiqueta: "Fecha límite" },
            { clave: "estado", etiqueta: "Estado" },
          ]}
          filas={filasAsignacion(perfil.pendientes)}
        />
      </Bloque>

      <Bloque titulo="Ejecutadas">
        <Table
          vacio="No hay capacitaciones ejecutadas."
          columnas={[
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "origen", etiqueta: "Origen" },
            { clave: "asig", etiqueta: "Asignación" },
            { clave: "limite", etiqueta: "Fecha límite" },
            { clave: "estado", etiqueta: "Estado" },
          ]}
          filas={filasAsignacion(perfil.ejecutadas)}
        />
      </Bloque>

      <Bloque titulo="Cumplimientos, evaluaciones y soportes">
        <Table
          vacio="No hay cumplimientos registrados."
          columnas={[
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "fecha", etiqueta: "Realización" },
            { clave: "resultado", etiqueta: "Resultado" },
            { clave: "nota", etiqueta: "Evaluación" },
            { clave: "vence", etiqueta: "Vencimiento" },
            { clave: "sop", etiqueta: "Soportes" },
          ]}
          filas={perfil.cumplimientos.map((item) => [
            etiquetaCapacitacion(item),
            formatoFecha(item.fecha_realizacion),
            item.resultado ?? "—",
            etiquetaNota(item),
            formatoFecha(item.fecha_vencimiento),
            <ListaEvidencias key={`s-${item.cumplimiento_id}`} soportes={item.soportes ?? []} />,
          ])}
        />
      </Bloque>

      <Bloque titulo="Historial de sesiones">
        <Table
          vacio="No hay participación en sesiones."
          columnas={[
            { clave: "fecha", etiqueta: "Fecha" },
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "asis", etiqueta: "Asistencia" },
            { clave: "sesion", etiqueta: "Estado de sesión" },
          ]}
          filas={perfil.historial_sesiones.map((item) => [
            formatoFecha(item.fecha),
            etiquetaCapacitacion(item),
            etiquetaAsistencia(item.estado_asistencia),
            item.sesion_estado || "—",
          ])}
        />
      </Bloque>

      <Bloque titulo="Alertas y vencimientos">
        <Table
          vacio="No hay alertas vigentes para este trabajador."
          columnas={[
            { clave: "cap", etiqueta: "Capacitación" },
            { clave: "vence", etiqueta: "Vencimiento" },
            { clave: "dias", etiqueta: "Días" },
            { clave: "estado", etiqueta: "Estado" },
          ]}
          filas={perfil.alertas.map((item) => [
            etiquetaCapacitacion(item),
            formatoFecha(item.fecha_vencimiento),
            String(item.dias_restantes),
            <Badge key="a" tono={tonoEstado(item.estado)}>
              {item.estado}
            </Badge>,
          ])}
        />
      </Bloque>
    </div>
  );
}
