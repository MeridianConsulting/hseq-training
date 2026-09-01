"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Table } from "@/components/ui/table";
import { apiGet, apiPost, apiPut, withQuery } from "@/lib/api";
import type {
  DetalleSesion,
  ParticipanteSesion,
  PreviewCumplimiento,
  ResultadoMasivoCumplimiento,
  ResultadoEvaluaciones,
  ResumenAsistencia,
  SesionCronograma,
} from "@/lib/tipos";

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
  const { puede } = useAuth();
  const [seleccionCump, setSeleccionCump] = useState<number[]>([]);
  const [fechaCump, setFechaCump] = useState(sesion.fecha ?? "");
  const [horasCump, setHorasCump] = useState("");
  const [previewCump, setPreviewCump] = useState<PreviewCumplimiento | null>(null);
  const [guardandoCump, setGuardandoCump] = useState(false);
  const [notasEval, setNotasEval] = useState<Record<number, string>>({});
  const [guardandoEval, setGuardandoEval] = useState(false);

  useEffect(() => {
    setFilas(filasDesde(sesion.participantes));
    setFechaCump(sesion.fecha ?? "");
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
  const evaluables = sesion.participantes.filter(
    (p) => p.estado_asistencia === "ASISTIO" || p.estado_asistencia === "TARDE",
  );
  const elegiblesCump = sesion.participantes.filter(
    (p) =>
      (p.estado_asistencia === "ASISTIO" || p.estado_asistencia === "TARDE") &&
      p.cumplimiento_resultado !== "APROBADO",
  );

  useEffect(() => {
    setNotasEval((prev) => {
      const siguiente: Record<number, string> = { ...prev };
      for (const p of evaluables) {
        if (siguiente[p.asignacion_id] === undefined) {
          siguiente[p.asignacion_id] =
            p.nota_evaluacion != null ? String(p.nota_evaluacion) : "";
        }
      }
      return siguiente;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sesion.participantes]);

  useEffect(() => {
    setSeleccionCump((prev) => {
      const ids = new Set(elegiblesCump.map((p) => p.asignacion_id));
      const keep = prev.filter((id) => ids.has(id));
      return keep.length > 0 ? keep : elegiblesCump.map((p) => p.asignacion_id);
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sesion.participantes]);

  useEffect(() => {
    if (seleccionCump.length === 0 || !fechaCump) {
      setPreviewCump(null);
      return;
    }
    const id = window.setTimeout(() => {
      void (async () => {
        const r = await apiGet<PreviewCumplimiento>(
          withQuery("/api/cumplimientos/previsualizar", {
            sesion_id: sesion.sesion_id,
            asignacion_ids: seleccionCump.join(","),
            fecha_realizacion: fechaCump,
          }),
        );
        setPreviewCump(r.success && r.data ? r.data : null);
      })();
    }, 250);
    return () => window.clearTimeout(id);
  }, [seleccionCump, fechaCump, sesion.sesion_id]);

  async function registrarCumplimiento(evento: FormEvent) {
    evento.preventDefault();
    setError(null);
    if (seleccionCump.length === 0) {
      setError("Seleccione al menos un trabajador que haya asistido o llegado tarde.");
      return;
    }
    if (!fechaCump) {
      setError("La fecha de realización es obligatoria.");
      return;
    }
    if (horasCump.trim() === "" || Number(horasCump) <= 0 || Number.isNaN(Number(horasCump))) {
      setError("Las horas efectivas deben ser un número mayor que cero.");
      return;
    }
    let notasPayload: Record<number, number> | undefined;
    if (sesion.requiere_evaluacion) {
      notasPayload = {};
      for (const id of seleccionCump) {
        const texto = (notasEval[id] ?? "").trim();
        if (texto === "") {
          setError("La nota es obligatoria.");
          return;
        }
        const n = Number(texto);
        if (Number.isNaN(n)) {
          setError("La nota debe ser numérica.");
          return;
        }
        if (n < 0 || n > 5) {
          setError("La nota está fuera del rango permitido.");
          return;
        }
        notasPayload[id] = n;
      }
    }
    if (!window.confirm(`¿Registrar para ${seleccionCump.length} trabajadores?`)) {
      return;
    }

    setGuardandoCump(true);
    const respuesta = await apiPost<ResultadoMasivoCumplimiento>("/api/cumplimientos/masivo", {
      sesion_id: sesion.sesion_id,
      asignacion_ids: seleccionCump,
      fecha_realizacion: fechaCump,
      resultado: "APROBADO",
      horas_efectivas: Number(horasCump),
      notas: notasPayload,
    });
    setGuardandoCump(false);
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible registrar el cumplimiento.");
      return;
    }

    const recarga = await apiGet<DetalleSesion>(`/api/sesiones/${sesion.sesion_id}`);
    if (recarga.success && recarga.data) {
      onGuardado(recarga.data, respuesta.message);
    } else {
      onGuardado(sesion, respuesta.message);
    }
  }

  async function guardarEvaluaciones(evento: FormEvent) {
    evento.preventDefault();
    setError(null);
    if (evaluables.length === 0) {
      setError("No hay asistentes para evaluar.");
      return;
    }
    const items: { asignacion_id: number; nota: number }[] = [];
    for (const p of evaluables) {
      const texto = (notasEval[p.asignacion_id] ?? "").trim();
      if (texto === "") {
        setError("La nota es obligatoria.");
        return;
      }
      const n = Number(texto);
      if (Number.isNaN(n)) {
        setError("La nota debe ser numérica.");
        return;
      }
      if (n < 0 || n > 5) {
        setError("La nota está fuera del rango permitido.");
        return;
      }
      items.push({ asignacion_id: p.asignacion_id, nota: n });
    }

    setGuardandoEval(true);
    const respuesta = await apiPost<ResultadoEvaluaciones>("/api/cumplimientos/evaluaciones", {
      sesion_id: sesion.sesion_id,
      items,
    });
    setGuardandoEval(false);
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible registrar la evaluación.");
      return;
    }
    const recarga = await apiGet<DetalleSesion>(`/api/sesiones/${sesion.sesion_id}`);
    if (recarga.success && recarga.data) {
      onGuardado(recarga.data, respuesta.message);
    } else {
      onGuardado(sesion, respuesta.message);
    }
  }

  function formatoVence(valor: string | null): string {
    if (!valor) return "Sin vencimiento";
    const [anio, mes, dia] = valor.slice(0, 10).split("-");
    if (!dia) return valor;
    return `${dia}/${mes}/${anio}`;
  }

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

      {puede("cumplimientos.crear") && sesion.requiere_evaluacion && evaluables.length > 0 && !cerrada ? (
        <div className="rounded-lg border border-slate-200 p-4">
          <h3 className="mb-3 text-sm font-semibold text-hseq-900">Registro de evaluaciones</h3>
          <p className="mb-3 text-sm text-slate-600">
            Nota mínima aprobatoria: {(sesion.nota_minima ?? 0).toFixed(2).replace(".", ",")} (escala 0 a 5).
            Solo asistentes y llegadas tarde.
          </p>
          <form className="space-y-3" onSubmit={(e) => void guardarEvaluaciones(e)}>
            <Table
              columnas={[
                { clave: "persona", etiqueta: "Trabajador" },
                { clave: "nota", etiqueta: "Nota" },
                { clave: "res", etiqueta: "Resultado" },
              ]}
              filas={evaluables.map((p) => {
                const texto = notasEval[p.asignacion_id] ?? "";
                const n = texto.trim() === "" ? null : Number(texto);
                const minima = sesion.nota_minima ?? 0;
                const ok = n !== null && !Number.isNaN(n) ? n >= minima : null;
                return [
                  <span key="n">
                    <span className="font-medium">{p.persona_nombre}</span>
                    {p.numero_documento ? (
                      <span className="ml-1 text-slate-500">{p.numero_documento}</span>
                    ) : null}
                  </span>,
                  <input
                    key="i"
                    type="number"
                    min={0}
                    max={5}
                    step="0.01"
                    className={inputClass}
                    value={texto}
                    onChange={(e) =>
                      setNotasEval((prev) => ({ ...prev, [p.asignacion_id]: e.target.value }))
                    }
                  />,
                  ok === null ? "—" : ok ? "Aprobado" : "No aprobado",
                ];
              })}
            />
            <Button type="submit" disabled={guardandoEval}>
              {guardandoEval ? "Guardando…" : "Guardar evaluaciones"}
            </Button>
          </form>
        </div>
      ) : null}

      {puede("cumplimientos.crear") && elegiblesCump.length > 0 && !cerrada ? (
        <div className="rounded-lg border border-slate-200 p-4">
          <h3 className="mb-3 text-sm font-semibold text-hseq-900">Registrar cumplimiento</h3>
          {sesion.requiere_certificado ? (
            <Alert tono="aviso">
              Esta capacitación requiere certificado. Complete cada trabajador de forma individual
              y adjunte su archivo en Cumplimientos. El registro masivo no está disponible.
            </Alert>
          ) : (
          <>
          <p className="mb-3 text-sm text-slate-600">
            Solo trabajadores que asistieron o llegaron tarde. El vencimiento se calcula con la
            periodicidad de la matriz y no se digita.
          </p>
          <form className="space-y-3" onSubmit={(e) => void registrarCumplimiento(e)}>
            <ul className="space-y-2">
              {elegiblesCump.map((p) => {
                const prev = previewCump?.items.find((i) => i.asignacion_id === p.asignacion_id);
                return (
                  <li key={p.asignacion_id}>
                    <label className="flex items-start gap-2 text-sm">
                      <input
                        type="checkbox"
                        className="mt-1"
                        checked={seleccionCump.includes(p.asignacion_id)}
                        onChange={(e) => {
                          setSeleccionCump((prevSel) =>
                            e.target.checked
                              ? [...prevSel, p.asignacion_id]
                              : prevSel.filter((id) => id !== p.asignacion_id),
                          );
                        }}
                      />
                      <span>
                        <span className="font-medium">{p.persona_nombre}</span>
                        {p.numero_documento ? (
                          <span className="ml-1 text-slate-500">{p.numero_documento}</span>
                        ) : null}
                        <span className="block text-xs text-slate-500">
                          Vence: {formatoVence(prev?.fecha_vencimiento ?? p.fecha_vencimiento)}
                          {prev?.etiqueta_periodicidad
                            ? ` · ${prev.etiqueta_periodicidad}`
                            : ""}
                        </span>
                      </span>
                    </label>
                  </li>
                );
              })}
            </ul>
            {previewCump?.aviso ? <Alert tono="aviso">{previewCump.aviso}</Alert> : null}
            <div className="grid gap-3 sm:grid-cols-3">
              <Field etiqueta="Fecha de realización">
                <input
                  type="date"
                  className={inputClass}
                  required
                  value={fechaCump}
                  onChange={(e) => setFechaCump(e.target.value)}
                />
              </Field>
              <Field etiqueta="Resultado">
                <select className={inputClass} value="APROBADO" disabled>
                  <option value="APROBADO">Aprobado</option>
                </select>
              </Field>
              <Field etiqueta="Horas efectivas">
                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  className={inputClass}
                  required
                  value={horasCump}
                  onChange={(e) => setHorasCump(e.target.value)}
                />
              </Field>
            </div>
            <Field etiqueta="Fecha de vencimiento">
              <input
                className={inputClass}
                readOnly
                disabled
                value={
                  previewCump?.periodicidades_distintas
                    ? "Varía por trabajador (ver listado)"
                    : formatoVence(previewCump?.items[0]?.fecha_vencimiento ?? null)
                }
              />
            </Field>
            <Button type="submit" disabled={guardandoCump}>
              {guardandoCump ? "Registrando…" : "Registrar cumplimiento"}
            </Button>
          </form>
          </>
          )}
        </div>
      ) : null}
    </div>
  );
}
