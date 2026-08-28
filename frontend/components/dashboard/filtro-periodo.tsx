"use client";

import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import type { TipoPeriodoDashboard } from "@/lib/tipos";

export type FiltroPeriodoValor = {
  tipo: TipoPeriodoDashboard;
  anio: number;
  mes: number;
  trimestre: number;
};

const MESES = [
  "Enero",
  "Febrero",
  "Marzo",
  "Abril",
  "Mayo",
  "Junio",
  "Julio",
  "Agosto",
  "Septiembre",
  "Octubre",
  "Noviembre",
  "Diciembre",
];

function aniosDisponibles(): number[] {
  const actual = new Date().getFullYear();
  const lista: number[] = [];
  for (let anio = actual - 2; anio <= actual + 1; anio += 1) {
    lista.push(anio);
  }
  return lista;
}

export function FiltroPeriodo({
  valor,
  onChange,
}: {
  valor: FiltroPeriodoValor;
  onChange: (siguiente: FiltroPeriodoValor) => void;
}) {
  return (
    <Filters>
      <Field etiqueta="Período">
        <select
          className={inputClass}
          value={valor.tipo}
          onChange={(evento) =>
            onChange({ ...valor, tipo: evento.target.value as TipoPeriodoDashboard })
          }
        >
          <option value="mensual">Mensual</option>
          <option value="trimestral">Trimestral</option>
          <option value="anual">Anual</option>
        </select>
      </Field>

      <Field etiqueta="Año">
        <select
          className={inputClass}
          value={valor.anio}
          onChange={(evento) => onChange({ ...valor, anio: Number(evento.target.value) })}
        >
          {aniosDisponibles().map((anio) => (
            <option key={anio} value={anio}>
              {anio}
            </option>
          ))}
        </select>
      </Field>

      {valor.tipo === "mensual" ? (
        <Field etiqueta="Mes">
          <select
            className={inputClass}
            value={valor.mes}
            onChange={(evento) => onChange({ ...valor, mes: Number(evento.target.value) })}
          >
            {MESES.map((nombre, indice) => (
              <option key={nombre} value={indice + 1}>
                {nombre}
              </option>
            ))}
          </select>
        </Field>
      ) : null}

      {valor.tipo === "trimestral" ? (
        <Field etiqueta="Trimestre">
          <select
            className={inputClass}
            value={valor.trimestre}
            onChange={(evento) => onChange({ ...valor, trimestre: Number(evento.target.value) })}
          >
            <option value={1}>Primer trimestre (ene–mar)</option>
            <option value={2}>Segundo trimestre (abr–jun)</option>
            <option value={3}>Tercer trimestre (jul–sep)</option>
            <option value={4}>Cuarto trimestre (oct–dic)</option>
          </select>
        </Field>
      ) : null}
    </Filters>
  );
}
