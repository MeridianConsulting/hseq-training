"use client";

import { useAuth } from "@/components/auth-provider";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { apiDownload } from "@/lib/api";
import type {
  FichaTrabajadorReporte,
  GrupoHistorial,
  PeriodoHistorial,
} from "@/lib/tipos";

const ORIGEN: Record<string, string> = {
  AUTOMATICA: "Automática (matriz)",
  MANUAL: "Manual",
  INDUCCION: "Inducción",
  REINDUCCION: "Reinducción",
};

const ESTADOS: Record<string, string> = {
  PENDIENTE: "Pendiente",
  PENDIENTE_PROXIMA_A_VENCER: "Pendiente próxima a vencer",
  PENDIENTE_VENCIDA: "Pendiente vencida",
  COMPLETADA: "Completada",
  PROXIMA_A_VENCER: "Próxima a vencer",
  VENCIDA: "Vencida",
};

function fecha(valor: unknown): string {
  if (typeof valor !== "string" || !valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function texto(valor: unknown): string {
  if (valor === null || valor === undefined || valor === "") return "—";
  return String(valor);
}

function rango(periodo: PeriodoHistorial): string {
  const desde = fecha(periodo.vigente_desde);
  const hasta = periodo.vigente_hasta ? fecha(periodo.vigente_hasta) : "Actual";
  return `${desde} – ${hasta}`;
}

function tonoEstado(estado: unknown): "ok" | "aviso" | "alto" | "neutral" {
  const clave = typeof estado === "string" ? estado : "";
  if (clave === "COMPLETADA" || clave === "PROXIMA_A_VENCER") return "ok";
  if (clave.includes("VENCIDA") || clave === "VENCIDA") return "alto";
  if (clave.includes("PROXIMA")) return "aviso";
  return "neutral";
}

export function FichaTrabajador({ trabajador }: { trabajador: FichaTrabajadorReporte }) {
  const campos = [
    ["Documento", trabajador.documento],
    ["Nombre", trabajador.nombre],
    ["Correo", trabajador.correo],
    ["Cargo actual", trabajador.cargo],
    ["Proyecto actual", trabajador.proyecto],
    ["Fecha de ingreso", fecha(trabajador.fecha_ingreso)],
    ["Estado actual", trabajador.estado],
  ];

  return (
    <Card className="mb-4">
      <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
        Información actual del trabajador
      </h2>
      <dl className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {campos.map(([etiqueta, valor]) => (
          <div key={etiqueta}>
            <dt className="text-xs text-slate-500">{etiqueta}</dt>
            <dd className="text-sm font-medium text-slate-800">{texto(valor)}</dd>
          </div>
        ))}
      </dl>
    </Card>
  );
}

export function ListaPeriodos({
  titulo,
  periodos,
  campo,
}: {
  titulo: string;
  periodos: PeriodoHistorial[];
  campo: "cargo" | "proyecto" | "proceso";
}) {
  return (
    <Card>
      <h3 className="text-sm font-semibold text-slate-800">{titulo}</h3>
      {periodos.length === 0 ? (
        <p className="mt-2 text-sm text-slate-500">Sin registros históricos.</p>
      ) : (
        <ul className="mt-3 space-y-2">
          {periodos.map((p, i) => (
            <li key={`${campo}-${i}`} className="text-sm">
              <span className="font-medium text-slate-800">{texto(p[campo])}</span>
              <span className="block text-xs text-slate-500">
                {rango(p)}
                {p.fuente === "asignaciones" ? " · según asignaciones" : ""}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

export function GruposCapacitacion({
  grupos,
  vacio,
}: {
  grupos: GrupoHistorial[];
  vacio: string;
}) {
  const { puede } = useAuth();
  const puedeDescargar = puede("cumplimientos.ver");

  if (grupos.length === 0) {
    return <p className="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">{vacio}</p>;
  }

  return (
    <div className="space-y-4">
      {grupos.map((grupo) => (
        <Card key={grupo.proyecto}>
          <div className="mb-3 flex items-baseline justify-between gap-3">
            <h3 className="text-base font-semibold text-hseq-900">{grupo.proyecto}</h3>
            <span className="text-xs text-slate-500">
              {grupo.asignadas} capacitación{grupo.asignadas === 1 ? "" : "es"}
            </span>
          </div>
          <ul className="divide-y divide-slate-100">
            {grupo.items.map((item) => (
              <FilaCapacitacion
                key={String(item.asignacion_id)}
                item={item}
                puedeDescargar={puedeDescargar}
              />
            ))}
          </ul>
        </Card>
      ))}
    </div>
  );
}

function FilaCapacitacion({
  item,
  puedeDescargar,
}: {
  item: Record<string, unknown>;
  puedeDescargar: boolean;
}) {
  const soportes = Array.isArray(item.soportes) ? item.soportes : [];
  const estado = typeof item.estado === "string" ? item.estado : "";

  return (
    <li className="py-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="font-medium text-slate-800">{texto(item.capacitacion)}</p>
          <p className="text-xs text-slate-500">
            {ORIGEN[String(item.origen ?? "")] ?? texto(item.origen)}
            {item.tipo ? ` · ${texto(item.tipo)}` : ""}
          </p>
        </div>
        <Badge tono={tonoEstado(estado)}>{ESTADOS[estado] ?? texto(estado)}</Badge>
      </div>
      <dl className="mt-2 grid gap-2 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <dt className="text-slate-400">Cargo al asignar</dt>
          <dd>{texto(item.cargo)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Proceso al asignar</dt>
          <dd>{texto(item.proceso)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Proyecto al asignar</dt>
          <dd>{texto(item.proyecto)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Asignación</dt>
          <dd>{fecha(item.fecha_asignacion)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Realización</dt>
          <dd>{fecha(item.fecha_realizacion)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Sesión</dt>
          <dd>{fecha(item.fecha_sesion)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Vencimiento</dt>
          <dd>{fecha(item.fecha_vencimiento)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Horas</dt>
          <dd>{item.horas_efectivas == null ? "—" : String(item.horas_efectivas)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Evaluación</dt>
          <dd>
            {texto(item.evaluacion_requerida)}
            {item.nota_evaluacion != null
              ? ` · Nota ${item.nota_evaluacion}${item.nota_minima != null ? ` (mín. ${item.nota_minima})` : ""}`
              : ""}
            {item.evaluacion_resultado ? ` · ${texto(item.evaluacion_resultado)}` : ""}
          </dd>
        </div>
        <div>
          <dt className="text-slate-400">Resultado</dt>
          <dd>{texto(item.resultado)}</dd>
        </div>
        <div className="sm:col-span-2">
          <dt className="text-slate-400">Evidencia</dt>
          <dd>
            {texto(item.evidencia)}
            {soportes.length > 0 ? (
              <span className="mt-1 block space-x-2">
                {soportes.map((raw) => {
                  const s = raw as { soporte_id: number; nombre_archivo: string };
                  if (!puedeDescargar) {
                    return (
                      <span key={s.soporte_id} className="text-slate-500">
                        {s.nombre_archivo}
                      </span>
                    );
                  }
                  return (
                    <button
                      key={s.soporte_id}
                      type="button"
                      className="text-hseq-800 underline-offset-2 hover:underline"
                      onClick={() =>
                        void apiDownload(
                          `/api/cumplimientos/soportes/${s.soporte_id}/archivo`,
                          s.nombre_archivo,
                        )
                      }
                    >
                      {s.nombre_archivo}
                    </button>
                  );
                })}
              </span>
            ) : null}
          </dd>
        </div>
      </dl>
    </li>
  );
}
