"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Table } from "@/components/ui/table";
import { apiGet, apiPost, apiPut, withQuery } from "@/lib/api";
import type { DetalleSesion, ParticipanteSesion, ResumenAsistencia, SesionCronograma } from "@/lib/tipos";

const ESTADOS: { valor: string; etiqueta: string }[] = [
  { valor: "CONVOCADO", etiqueta: "Pendiente" },
  { valor: "ASISTIO", etiqueta: "Asistió" },
  { valor: "TARDE", etiqueta: "Llegó tarde" },
  { valor: "AUSENTE", etiqueta: "Ausente" },
];

type FilaAsistencia = {
  asignacion_id: number;
  estado_asistencia: string;
  motivo_ausencia: string;
  observacion: string;
};

function filasDesde(participantes: ParticipanteSesion[]): FilaAsistencia[] {
  return participantes.map((p) => ({
    asignacion_id: p.asignacion_id,
    estado_asistencia: p.estado_asistencia || "CONVOCADO",
    motivo_ausencia: p.motivo_ausencia ?? "",
    observacion: p.observacion ?? "",
  }));
}

function resumenLocal(filas: FilaAsistencia[]): ResumenAsistencia {
  const r: ResumenAsistencia = {
    convocados: filas.length,
    asistieron: 0,
    tarde: 0,
    ausentes: 0,
    pendientes: 0,
  };
  for (const fila of filas) {
    if (fila.estado_asistencia === "ASISTIO") r.asistieron++;
    else if (fila.estado_asistencia === "TARDE") r.tarde++;
    else if (fila.estado_asistencia === "AUSENTE") r.ausentes++;
    else r.pendientes++;
  }
  return r;
}

export function FormularioAsistencia({
  sesion,
  puedeEditar,
  onGuardado,
}: {
  sesion: DetalleSesion;
  puedeEditar: boolean;
  onGuardado: (sesion: DetalleSesion, mensaje: string) => void;
}) {
  const [filas, setFilas] = useState<FilaAsistencia[]>(() => filasDesde(sesion.participantes));
  const [error, setError] = useState<string | null>(null);
  const [guardando, setGuardando] = useState(false);
  const [reprogramarAbierto, setReprogramarAbierto] = useState(false);
  const [seleccionAusentes, setSeleccionAusentes] = useState<number[]>([]);
  const [destinoId, setDestinoId] = useState("");
  const [destinos, setDestinos] = useState<SesionCronograma[]>([]);
  const [reprogramando, setReprogramando] = useState(false);

  useEffect(() => {
    setFilas(filasDesde(sesion.participantes));
  }, [sesion]);

  const resumen = useMemo(() => resumenLocal(filas), [filas]);
  const cerrada = sesion.estado === "CANCELADA";
  const editable = puedeEditar && !cerrada;

  function actualizar(asignacionId: number, cambio: Partial<FilaAsistencia>) {
    setFilas((prev) =>
      prev.map((fila) => (fila.asignacion_id === asignacionId ? { ...fila, ...cambio } : fila)),
    );
  }

  async function guardar(evento: FormEvent) {
    evento.preventDefault();
    setError(null);

    for (const fila of filas) {
      if (fila.estado_asistencia === "AUSENTE" && fila.motivo_ausencia.trim() === "") {
        setError("Debe registrar la razón de ausencia.");
        return;
      }
    }

    setGuardando(true);
    const respuesta = await apiPut<DetalleSesion>(`/api/sesiones/${sesion.sesion_id}/asistencia`, {
      items: filas.map((fila) => ({
        asignacion_id: fila.asignacion_id,
        estado_asistencia: fila.estado_asistencia,
        motivo_ausencia: fila.estado_asistencia === "AUSENTE" ? fila.motivo_ausencia.trim() : null,
        observacion: fila.observacion.trim() || null,
      })),
    });
    setGuardando(false);

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible guardar la asistencia.");
      return;
    }

    setReprogramarAbierto((respuesta.data.resumen?.ausentes ?? 0) > 0);
    onGuardado(respuesta.data, respuesta.message);
  }

  async function cargarDestinos() {
    if (!sesion.plan_detalle_id) {
      setDestinos([]);
      return;
    }
    const respuesta = await apiGet<{ sesiones: SesionCronograma[] }>(
      withQuery("/api/sesiones", { plan_detalle_id: sesion.plan_detalle_id }),
    );
    const lista = (respuesta.data?.sesiones ?? []).filter(
      (s) => s.sesion_id !== sesion.sesion_id && s.estado !== "CANCELADA",
    );
    setDestinos(lista);
  }

  async function reprogramar(evento: FormEvent) {
    evento.preventDefault();
    setError(null);
    if (seleccionAusentes.length === 0) {
      setError("Seleccione al menos un trabajador ausente.");
      return;
    }
    if (!destinoId) {
      setError("Seleccione la sesión destino.");
      return;
    }

    setReprogramando(true);
    const respuesta = await apiPost<DetalleSesion>(`/api/sesiones/${destinoId}/reprogramar`, {
      origen_sesion_id: sesion.sesion_id,
      asignacion_ids: seleccionAusentes,
    });
    setReprogramando(false);

    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible reprogramar.");
      return;
    }

    const recarga = await apiGet<DetalleSesion>(`/api/sesiones/${sesion.sesion_id}`);
    if (recarga.success && recarga.data) {
      onGuardado(recarga.data, respuesta.message);
    } else {
      onGuardado(sesion, respuesta.message);
    }
    setSeleccionAusentes([]);
    setDestinoId("");
  }

  const ausentesGuardados = sesion.participantes.filter((p) => p.estado_asistencia === "AUSENTE");

  return (
    <div className="space-y-4">
      {error ? <Alert tono="error">{error}</Alert> : null}
      <form className="space-y-4" onSubmit={(e) => void guardar(e)}>
      {cerrada ? (
        <Alert tono="aviso">Esta sesión está cancelada. No es posible registrar asistencia.</Alert>
      ) : null}

      <div className="flex flex-wrap gap-2 text-sm">
        <Badge tono="neutral">Convocados: {resumen.convocados}</Badge>
        <Badge tono="ok">Asistieron: {resumen.asistieron}</Badge>
        <Badge tono="aviso">Llegaron tarde: {resumen.tarde}</Badge>
        <Badge tono="alto">Ausentes: {resumen.ausentes}</Badge>
        <Badge tono="neutral">Pendientes: {resumen.pendientes}</Badge>
      </div>

      <Table
        columnas={[
          { clave: "doc", etiqueta: "Documento" },
          { clave: "nombre", etiqueta: "Trabajador" },
          { clave: "estado", etiqueta: "Asistencia" },
          { clave: "motivo", etiqueta: "Razón de ausencia" },
          { clave: "obs", etiqueta: "Observación" },
        ]}
        filas={sesion.participantes.map((p) => {
          const fila = filas.find((f) => f.asignacion_id === p.asignacion_id) ?? {
            asignacion_id: p.asignacion_id,
            estado_asistencia: p.estado_asistencia,
            motivo_ausencia: "",
            observacion: "",
          };
          return [
            p.numero_documento || "—",
            p.persona_nombre || `Persona ${p.persona_id_ext}`,
            <div key="e" className="flex flex-col gap-1">
              {ESTADOS.map((op) => (
                <label key={op.valor} className="inline-flex items-center gap-2 text-sm">
                  <input
                    type="radio"
                    name={`asistencia-${p.asignacion_id}`}
                    value={op.valor}
                    checked={fila.estado_asistencia === op.valor}
                    disabled={!editable}
                    onChange={() => actualizar(p.asignacion_id, { estado_asistencia: op.valor })}
                  />
                  {op.etiqueta}
                </label>
              ))}
            </div>,
            fila.estado_asistencia === "AUSENTE" ? (
              <input
                key="m"
                className={inputClass}
                value={fila.motivo_ausencia}
                disabled={!editable}
                required
                placeholder="Razón de ausencia"
                onChange={(e) => actualizar(p.asignacion_id, { motivo_ausencia: e.target.value })}
              />
            ) : (
              "—"
            ),
            <input
              key="o"
              className={inputClass}
              value={fila.observacion}
              disabled={!editable}
              placeholder="Opcional"
              onChange={(e) => actualizar(p.asignacion_id, { observacion: e.target.value })}
            />,
          ];
        })}
      />

      {editable ? (
        <div className="flex justify-end">
          <Button type="submit" disabled={guardando}>
            {guardando ? "Guardando…" : "Guardar asistencia"}
          </Button>
        </div>
      ) : null}
      </form>

      {ausentesGuardados.length > 0 && puedeEditar && !cerrada ? (
        <div className="rounded-lg border border-slate-200 p-4">
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 className="text-sm font-semibold text-hseq-900">
              Disponibles para reprogramación ({ausentesGuardados.length})
            </h3>
            <Button
              type="button"
              variante="secondary"
              onClick={() => {
                const abrir = !reprogramarAbierto;
                setReprogramarAbierto(abrir);
                if (abrir) {
                  void cargarDestinos();
                }
              }}
            >
              {reprogramarAbierto ? "Ocultar ausentes" : "Ver / reprogramar ausentes"}
            </Button>
          </div>

          {reprogramarAbierto ? (
            <form className="space-y-3" onSubmit={(e) => void reprogramar(e)}>
              <ul className="space-y-2">
                {ausentesGuardados.map((p) => (
                  <li key={p.asignacion_id}>
                    <label className="flex items-start gap-2 text-sm">
                      <input
                        type="checkbox"
                        className="mt-1"
                        checked={seleccionAusentes.includes(p.asignacion_id)}
                        onChange={(e) => {
                          setSeleccionAusentes((prev) =>
                            e.target.checked
                              ? [...prev, p.asignacion_id]
                              : prev.filter((id) => id !== p.asignacion_id),
                          );
                        }}
                      />
                      <span>
                        <span className="font-medium">{p.persona_nombre}</span>
                        {p.numero_documento ? (
                          <span className="ml-1 text-slate-500">{p.numero_documento}</span>
                        ) : null}
                        {p.motivo_ausencia ? (
                          <span className="block text-xs text-slate-500">Razón: {p.motivo_ausencia}</span>
                        ) : null}
                      </span>
                    </label>
                  </li>
                ))}
              </ul>
              <Field etiqueta="Sesión destino">
                <select
                  className={inputClass}
                  value={destinoId}
                  onChange={(e) => setDestinoId(e.target.value)}
                >
                  <option value="">Seleccione una sesión existente</option>
                  {destinos.map((d) => (
                    <option key={d.sesion_id} value={d.sesion_id}>
                      {d.fecha} {d.hora} · cupos {d.disponibles}/{d.cupo_maximo} · {d.estado}
                    </option>
                  ))}
                </select>
              </Field>
              <p className="text-xs text-slate-500">
                Si necesita una sesión nueva, créela en el Tablero de Cronograma y vuelva a reprogramar.
                No se crea una asignación nueva.
              </p>
              <Button type="submit" disabled={reprogramando}>
                {reprogramando ? "Reprogramando…" : "Reprogramar seleccionados"}
              </Button>
            </form>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
