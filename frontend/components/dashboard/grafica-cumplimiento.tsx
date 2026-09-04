import { Card } from "@/components/ui/card";
import type { KpiCumplimiento, KpiEficacia, KpiHoras, KpiSoportes } from "@/lib/tipos";

export function GraficaCumplimiento({
  titulo,
  descripcion,
  kpi,
}: {
  titulo: string;
  descripcion: string;
  kpi: KpiCumplimiento;
}) {
  const sinInformacion = kpi.programado === 0 && kpi.ejecutado === 0;
  const maximo = Math.max(kpi.programado, kpi.ejecutado, 1);
  const altoProgramado = (kpi.programado / maximo) * 120;
  const altoEjecutado = (kpi.ejecutado / maximo) * 120;

  return (
    <Card>
      <h3 className="text-sm font-semibold text-hseq-900">{titulo}</h3>
      <p className="mt-1 text-xs text-slate-500">{descripcion}</p>

      {sinInformacion ? (
        <p className="mt-8 text-center text-sm text-slate-500">Sin información</p>
      ) : (
        <>
          <div className="mt-4 flex items-end justify-center gap-8">
            <BarraSvg
              etiqueta="Programado"
              valor={kpi.programado}
              alto={altoProgramado}
              color="#0e7490"
            />
            <BarraSvg
              etiqueta="Ejecutado"
              valor={kpi.ejecutado}
              alto={altoEjecutado}
              color="#14b8a6"
            />
          </div>
          <p className="mt-4 text-center text-sm font-medium text-hseq-800">
            {kpi.sin_programado
              ? "Sin programado"
              : `${formatoNumero(kpi.porcentaje)} % de cobertura`}
          </p>
        </>
      )}
    </Card>
  );
}

function BarraSvg({
  etiqueta,
  valor,
  alto,
  color,
}: {
  etiqueta: string;
  valor: number;
  alto: number;
  color: string;
}) {
  const altura = Math.max(alto, valor > 0 ? 4 : 0);

  return (
    <div className="flex w-20 flex-col items-center">
      <span className="mb-1 text-sm font-semibold text-hseq-900">{valor}</span>
      <svg width="48" height="128" viewBox="0 0 48 128" aria-hidden="true">
        <rect x="8" y="0" width="32" height="128" rx="6" fill="#f1f5f9" />
        <rect x="8" y={128 - altura} width="32" height={altura} rx="6" fill={color} />
      </svg>
      <span className="mt-2 text-xs text-slate-500">{etiqueta}</span>
    </div>
  );
}

export function TarjetaEficacia({
  titulo,
  descripcion,
  kpi,
}: {
  titulo: string;
  descripcion: string;
  kpi: KpiEficacia;
}) {
  return (
    <Card>
      <h3 className="text-sm font-semibold text-hseq-900">{titulo}</h3>
      <p className="mt-1 text-xs text-slate-500">{descripcion}</p>
      {kpi.evaluaciones === 0 || kpi.promedio === null ? (
        <p className="mt-8 text-center text-sm text-slate-500">Sin evaluaciones</p>
      ) : (
        <>
          <p className="mt-6 text-center text-3xl font-semibold text-hseq-900">
            {formatoNumero(kpi.promedio, 2)}
          </p>
          <p className="mt-2 text-center text-xs text-slate-500">
            Promedio de {kpi.evaluaciones} evaluación{kpi.evaluaciones === 1 ? "" : "es"}
          </p>
          <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
            <div
              className="h-2 rounded-full bg-hseq-600"
              style={{ width: `${Math.min(100, Math.max(0, (kpi.promedio / 5) * 100))}%` }}
            />
          </div>
        </>
      )}
    </Card>
  );
}

export function TarjetaSoportes({ kpi }: { kpi: KpiSoportes }) {
  return (
    <Card>
      <h3 className="text-sm font-semibold text-hseq-900">Cumplimiento de soportes</h3>
      <p className="mt-1 text-xs text-slate-500">
        Capacitaciones que requieren certificado/soporte. Con soporte = cumple; sin soporte =
        pendiente.
      </p>
      {kpi.requieren === 0 ? (
        <p className="mt-8 text-center text-sm text-slate-500">Sin requerimientos en el período</p>
      ) : (
        <div className="mt-4 grid grid-cols-3 gap-3 text-center">
          <div>
            <p className="text-2xl font-semibold text-hseq-900">{kpi.con_soporte}</p>
            <p className="text-xs text-slate-500">Con soporte</p>
          </div>
          <div>
            <p className="text-2xl font-semibold text-slate-700">{kpi.pendientes}</p>
            <p className="text-xs text-slate-500">Pendientes</p>
          </div>
          <div>
            <p className="text-2xl font-semibold text-hseq-800">
              {kpi.porcentaje === null ? "—" : `${formatoNumero(kpi.porcentaje)} %`}
            </p>
            <p className="text-xs text-slate-500">Cumplimiento</p>
          </div>
        </div>
      )}
    </Card>
  );
}

export function TarjetaHoras({
  titulo,
  kpi,
}: {
  titulo: string;
  kpi: KpiHoras;
}) {
  const maximo = Math.max(kpi.programadas, kpi.ejecutadas, 1);
  const anchoProgramadas = (kpi.programadas / maximo) * 100;
  const anchoEjecutadas = (kpi.ejecutadas / maximo) * 100;

  return (
    <Card>
      <h3 className="text-sm font-semibold text-hseq-900">{titulo}</h3>
      <div className="mt-4 space-y-3">
        <BarraHoras etiqueta="Programadas" valor={kpi.programadas} ancho={anchoProgramadas} color="#0e7490" />
        <BarraHoras etiqueta="Ejecutadas" valor={kpi.ejecutadas} ancho={anchoEjecutadas} color="#14b8a6" />
      </div>
    </Card>
  );
}

function BarraHoras({
  etiqueta,
  valor,
  ancho,
  color,
}: {
  etiqueta: string;
  valor: number;
  ancho: number;
  color: string;
}) {
  return (
    <div>
      <div className="mb-1 flex items-baseline justify-between gap-2">
        <p className="text-sm text-slate-600">{etiqueta}</p>
        <p className="text-sm font-semibold text-hseq-900">{formatoNumero(valor, 1)} h</p>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
        <div className="h-2 rounded-full" style={{ width: `${ancho}%`, backgroundColor: color }} />
      </div>
    </div>
  );
}

function formatoNumero(valor: number | null | undefined, decimales = 1): string {
  if (valor === null || valor === undefined) {
    return "—";
  }
  return valor.toFixed(decimales).replace(".", ",");
}
