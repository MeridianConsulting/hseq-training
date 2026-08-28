"use client";

import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import type { ItemCronograma } from "@/lib/tipos";

const LIMITE_OBJETIVO = 160;

export function formatoHoras(horas: number | null): string {
  if (horas === null) {
    return "Sin dedicación estimada";
  }

  const entero = Number.isInteger(horas);
  const texto = entero ? String(horas) : horas.toFixed(1).replace(".", ",");
  return `${texto} ${horas === 1 ? "hora" : "horas"}`;
}

function tonoMetodologia(nombre: string | null) {
  if (!nombre) return "neutral" as const;
  const clave = nombre.toUpperCase();
  if (clave.includes("VIRTUAL")) return "bajo" as const;
  if (clave.includes("PRESENCIAL")) return "ok" as const;
  return "aviso" as const;
}

export function TarjetaCronograma({ item }: { item: ItemCronograma }) {
  const [abierta, setAbierta] = useState(false);
  const [objetivoCompleto, setObjetivoCompleto] = useState(false);
  const objetivoLargo = item.objetivo.length > LIMITE_OBJETIVO;
  const objetivoVisible =
    !objetivoLargo || objetivoCompleto
      ? item.objetivo
      : `${item.objetivo.slice(0, LIMITE_OBJETIVO).trim()}…`;

  return (
    <Card>
      <h3 className="text-base font-semibold text-hseq-900">{item.tema}</h3>
      <p className="mt-1 text-xs text-slate-500">{item.codigo}</p>
      <div className="mt-3 flex flex-wrap items-center gap-2">
        <span className="text-sm text-slate-700">{formatoHoras(item.horas)}</span>
        <Badge tono={tonoMetodologia(item.metodologia)}>
          {item.metodologia ?? "Sin metodología"}
        </Badge>
      </div>

      {abierta ? (
        <dl className="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm">
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">Tema</dt>
            <dd className="mt-1 text-slate-800">{item.tema}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">Objetivo</dt>
            <dd className="mt-1 whitespace-pre-wrap text-slate-700">{objetivoVisible}</dd>
            {objetivoLargo ? (
              <button
                type="button"
                className="mt-1 text-xs font-medium text-hseq-700 hover:underline"
                onClick={() => setObjetivoCompleto((valor) => !valor)}
              >
                {objetivoCompleto ? "Ver menos" : "Ver más"}
              </button>
            ) : null}
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
                Dedicación estimada
              </dt>
              <dd className="mt-1 text-slate-800">{formatoHoras(item.horas)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
                Metodología
              </dt>
              <dd className="mt-1 text-slate-800">{item.metodologia ?? "Sin metodología"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
                Mes de programación
              </dt>
              <dd className="mt-1 text-slate-800">
                {item.mes_nombre} {item.anio}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">Proceso</dt>
              <dd className="mt-1 text-slate-800">{item.proceso_nombre ?? "Sin proceso"}</dd>
            </div>
          </div>
        </dl>
      ) : null}

      <div className="mt-4">
        <Button type="button" variante="ghost" className="px-0" onClick={() => setAbierta((v) => !v)}>
          {abierta ? "Ocultar detalles" : "Ver detalles"}
        </Button>
      </div>
    </Card>
  );
}
