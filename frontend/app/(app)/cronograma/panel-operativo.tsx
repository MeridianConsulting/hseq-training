"use client";

import { useEffect, useState } from "react";
import { FormularioAsistencia } from "@/app/(app)/sesiones/formulario-asistencia";
import { ListaEvidencias, subirSoportes } from "@/app/(app)/cumplimientos/evidencias";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { apiGet, apiPost } from "@/lib/api";
import type { DetalleSesion, ItemCronograma, ParticipanteSesion, SoporteCumplimiento } from "@/lib/tipos";

function asistio(p: ParticipanteSesion): boolean {
  return p.estado_asistencia === "ASISTIO" || p.estado_asistencia === "TARDE";
}

export function PanelOperativo({
  item,
  sesionId,
  onMensaje,
  onCerrado,
}: {
  item: ItemCronograma;
  sesionId: number;
  onMensaje: (texto: string) => void;
  onCerrado: () => void;
}) {
  const { puede } = useAuth();
  const [sesion, setSesion] = useState<DetalleSesion | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(true);
  const [finalizando, setFinalizando] = useState(false);
  const [soportes, setSoportes] = useState<Record<number, SoporteCumplimiento[]>>({});
  const [subiendo, setSubiendo] = useState<number | null>(null);

  async function cargar(id: number) {
    setCargando(true);
    const respuesta = await apiGet<DetalleSesion>(`/api/sesiones/${id}`);
    if (respuesta.cancelada) {
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
    await cargarSoportes(respuesta.data);
  }

  async function cargarSoportes(detalle: DetalleSesion) {
    if (!detalle.requiere_certificado) {
      setSoportes({});
      return;
    }
    const ids = detalle.participantes
      .filter((p) => asistio(p) && p.cumplimiento_id)
      .map((p) => p.cumplimiento_id as number);
    const mapa: Record<number, SoporteCumplimiento[]> = {};
    await Promise.all(
      ids.map(async (cumplimientoId) => {
        const r = await apiGet<SoporteCumplimiento[]>(`/api/cumplimientos/${cumplimientoId}/soportes`);
        mapa[cumplimientoId] = r.success && Array.isArray(r.data) ? r.data : [];
      }),
    );
    setSoportes(mapa);
  }

  useEffect(() => {
    void cargar(sesionId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sesionId]);

  async function finalizar() {
    if (!sesion) return;
    if (!window.confirm("¿Finalizar esta capacitación? Después no se podrá editar asistencia, evaluaciones ni soportes.")) {
      return;
    }
    setFinalizando(true);
    const respuesta = await apiPost<DetalleSesion>(`/api/sesiones/${sesion.sesion_id}/finalizar`, {});
    setFinalizando(false);
    if (respuesta.cancelada) {
      return;
    }
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible finalizar la capacitación.");
      return;
    }
    onMensaje(respuesta.message || "Capacitación finalizada correctamente.");
    onCerrado();
  }

  async function adjuntar(cumplimientoId: number, archivos: FileList | null) {
    if (!archivos || archivos.length === 0) return;
    setSubiendo(cumplimientoId);
    const err = await subirSoportes(cumplimientoId, Array.from(archivos));
    setSubiendo(null);
    if (err) {
      setError(err);
      return;
    }
    if (sesion) {
      await cargarSoportes(sesion);
    }
  }

  if (cargando && !sesion) {
    return <p className="text-sm text-slate-500">Cargando panel operativo…</p>;
  }
  if (!sesion) {
    return error ? <Alert tono="error">{error}</Alert> : null;
  }

  const cerrada = sesion.estado === "EJECUTADA" || sesion.estado === "CANCELADA";
  const conSoporte = sesion.participantes.filter((p) => asistio(p) && p.cumplimiento_id);

  return (
    <div className="space-y-6">
      <p className="text-sm text-slate-600">
        {item.codigo} — {item.tema}. Fecha programada: {item.fecha_programada ?? "—"}.
      </p>
      {error ? <Alert tono="error">{error}</Alert> : null}

      <FormularioAsistencia
        sesion={sesion}
        puedeEditar={puede("sesiones.editar")}
        modoOperativo
        onGuardado={(actualizada, mensaje) => {
          setSesion(actualizada);
          setError(null);
          onMensaje(mensaje);
          void cargarSoportes(actualizada);
        }}
      />

      {sesion.requiere_certificado ? (
        <div className="rounded-lg border border-slate-200 p-4">
          <h3 className="mb-3 text-sm font-semibold text-hseq-900">Soportes</h3>
          {conSoporte.length === 0 ? (
            <p className="text-sm text-slate-500">
              Guarde la asistencia de los trabajadores para adjuntar soportes.
            </p>
          ) : (
            <ul className="space-y-4">
              {conSoporte.map((p) => {
                const cid = p.cumplimiento_id as number;
                return (
                  <li key={cid} className="rounded-md border border-slate-100 p-3">
                    <p className="mb-2 text-sm font-medium text-hseq-900">
                      {p.persona_nombre}
                      {p.numero_documento ? (
                        <span className="ml-1 font-normal text-slate-500">{p.numero_documento}</span>
                      ) : null}
                    </p>
                    <ListaEvidencias
                      soportes={soportes[cid] ?? []}
                      puedeEliminar={puede("cumplimientos.editar") && !cerrada}
                      onEliminado={() => void cargarSoportes(sesion)}
                      onError={setError}
                    />
                    {puede("cumplimientos.crear") && !cerrada ? (
                      <label className="mt-2 block text-sm">
                        <span className="sr-only">Adjuntar soporte</span>
                        <input
                          type="file"
                          className="text-sm"
                          disabled={subiendo === cid}
                          onChange={(e) => {
                            void adjuntar(cid, e.target.files);
                            e.target.value = "";
                          }}
                        />
                      </label>
                    ) : null}
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      ) : null}

      {puede("sesiones.editar") && !cerrada ? (
        <div className="flex justify-end">
          <Button type="button" onClick={() => void finalizar()} disabled={finalizando}>
            {finalizando ? "Finalizando…" : "Finalizar capacitación"}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
