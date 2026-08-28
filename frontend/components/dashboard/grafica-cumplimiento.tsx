import { Card } from "@/components/ui/card";
import type { KpiCumplimiento, TemaEficacia, TemaHoras } from "@/lib/tipos";

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
      <h2 className="text-sm font-semibold text-hseq-900">{titulo}</h2>
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
              : `${kpi.porcentaje?.toFixed(1).replace(".", ",")} % de cumplimiento`}
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

export function GraficaEficacia({ temas }: { temas: TemaEficacia[] }) {
  return (
    <Card>
      <h2 className="text-sm font-semibold text-hseq-900">Eficacia por tema</h2>
      <p className="mt-1 text-xs text-slate-500">Promedio de nota (escala 0 a 5). Sin nota no entra al cálculo.</p>
      {temas.length === 0 ? (
        <p className="mt-8 text-center text-sm text-slate-500">Sin información</p>
      ) : (
        <ul className="mt-4 space-y-3">
          {temas.map((tema) => {
            const ancho = Math.min(100, Math.max(0, (tema.promedio / 5) * 100));
            return (
              <li key={tema.capacitacion_id}>
                <div className="mb-1 flex items-baseline justify-between gap-2">
                  <p className="text-sm text-slate-700">
                    <span className="font-medium text-hseq-900">{tema.codigo}</span> {tema.nombre}
                  </p>
                  <p className="shrink-0 text-sm font-semibold text-hseq-800">
                    {tema.promedio.toFixed(2).replace(".", ",")}
                    <span className="ml-1 text-xs font-normal text-slate-500">
                      ({tema.evaluaciones})
                    </span>
                  </p>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-2 rounded-full bg-hseq-600" style={{ width: `${ancho}%` }} />
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </Card>
  );
}

export function GraficaHoras({ temas }: { temas: TemaHoras[] }) {
  const maximo = Math.max(...temas.map((tema) => tema.horas), 0);

  return (
    <Card>
      <h2 className="text-sm font-semibold text-hseq-900">Horas ejecutadas por tema</h2>
      <p className="mt-1 text-xs text-slate-500">Suma de horas efectivas registradas en cumplimientos del período.</p>
      {temas.length === 0 ? (
        <p className="mt-8 text-center text-sm text-slate-500">Sin información</p>
      ) : (
        <ul className="mt-4 space-y-3">
          {temas.map((tema) => {
            const ancho = maximo > 0 ? (tema.horas / maximo) * 100 : 0;
            return (
              <li key={tema.capacitacion_id}>
                <div className="mb-1 flex items-baseline justify-between gap-2">
                  <p className="text-sm text-slate-700">
                    <span className="font-medium text-hseq-900">{tema.codigo}</span> {tema.nombre}
                  </p>
                  <p className="shrink-0 text-sm font-semibold text-hseq-800">
                    {tema.horas.toFixed(1).replace(".", ",")} h
                  </p>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-2 rounded-full bg-hseq-500" style={{ width: `${ancho}%` }} />
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </Card>
  );
}
