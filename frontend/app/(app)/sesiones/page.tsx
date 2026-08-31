"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { FormularioAsistencia } from "@/app/(app)/sesiones/formulario-asistencia";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { apiGet } from "@/lib/api";
import type { DetalleSesion } from "@/lib/tipos";

function leerSesionId(): number {
  if (typeof window === "undefined") {
    return 0;
  }
  const id = Number(new URLSearchParams(window.location.search).get("sesion_id") || 0);
  return Number.isFinite(id) && id > 0 ? id : 0;
}

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

export default function SesionesPage() {
  return (
    <RequierePermiso permiso="sesiones.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const [sesionId, setSesionId] = useState(0);
  const [listo, setListo] = useState(false);
  const [sesion, setSesion] = useState<DetalleSesion | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [cargando, setCargando] = useState(false);

  useEffect(() => {
    setSesionId(leerSesionId());
    setListo(true);
  }, []);

  useEffect(() => {
    if (!listo) {
      return;
    }
    if (sesionId < 1) {
      setSesion(null);
      setCargando(false);
      return;
    }

    const abortado = { actual: false };
    setCargando(true);
    void (async () => {
      const respuesta = await apiGet<DetalleSesion>(`/api/sesiones/${sesionId}`);
      if (abortado.actual) {
        return;
      }
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar la sesión.");
        setSesion(null);
        setCargando(false);
        return;
      }
      setSesion(respuesta.data);
      setError(null);
      setCargando(false);
    })();

    return () => {
      abortado.actual = true;
    };
  }, [sesionId, listo]);

  return (
    <>
      <PageHeader
        titulo="Sesiones y asistencia"
        descripcion="Registre el resultado de la capacitación realizada fuera de la plataforma. Los ausentes quedan disponibles para reprogramación."
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      {!listo || cargando ? (
        <p className="text-sm text-slate-500">Cargando…</p>
      ) : sesionId < 1 ? (
        <Alert tono="info">
          Seleccione una sesión desde el{" "}
          <Link href="/cronograma" className="font-medium underline underline-offset-2">
            Tablero de Cronograma
          </Link>{" "}
          para registrar asistencia. No se crean sesiones desde este módulo.
        </Alert>
      ) : sesion === null ? null : (
        <div className="space-y-6">
          <Card>
            <h2 className="text-base font-semibold text-hseq-900">
              {sesion.capacitacion_codigo} — {sesion.capacitacion_nombre}
            </h2>
            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">Fecha</dt>
                <dd className="mt-1 text-slate-800">
                  {formatoFecha(sesion.fecha)} {sesion.hora ?? ""}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">Modalidad</dt>
                <dd className="mt-1 text-slate-800">{sesion.modalidad_nombre ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">Ubicación / enlace</dt>
                <dd className="mt-1 text-slate-800">
                  {sesion.ubicacion_nombre ?? sesion.enlace_virtual ?? "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">Proveedor</dt>
                <dd className="mt-1 text-slate-800">{sesion.proveedor_nombre ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">Cupo</dt>
                <dd className="mt-1 text-slate-800">
                  {sesion.convocados} / {sesion.cupo_maximo}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">Estado de la sesión</dt>
                <dd className="mt-1">
                  <Badge tono={sesion.estado === "CANCELADA" ? "alto" : "neutral"}>{sesion.estado}</Badge>
                </dd>
              </div>
            </dl>
          </Card>

          {sesion.participantes.length === 0 ? (
            <Alert tono="aviso">Esta sesión no tiene trabajadores convocados.</Alert>
          ) : (
            <FormularioAsistencia
              sesion={sesion}
              puedeEditar={puede("sesiones.editar")}
              onGuardado={(actualizada, texto) => {
                setSesion(actualizada);
                setMensaje(texto);
                setError(null);
              }}
            />
          )}
        </div>
      )}
    </>
  );
}
