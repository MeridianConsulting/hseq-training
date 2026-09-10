"use client";

import { procesoPermiteFiltroProyecto } from "@/components/dashboard/filtro-periodo";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import type { ProcesoCronograma, TipoPeriodoDashboard } from "@/lib/tipos";

export type FiltroCronogramaValor = {
  tipo: TipoPeriodoDashboard;
  anio: number;
  mes: number;
  trimestre: number;
  semestre: number;
  procesoId: string;
  proyecto: string;
  buscar: string;
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

export function FiltroCronograma({
  valor,
  procesos,
  proyectos = [],
  onChange,
}: {
  valor: FiltroCronogramaValor;
  procesos: ProcesoCronograma[];
  proyectos?: string[];
  onChange: (siguiente: FiltroCronogramaValor) => void;
}) {
  const esProyectos = procesoPermiteFiltroProyecto(valor.procesoId, procesos);

  return (
    <Filters>
      <Field etiqueta="Proceso">
        <select
          className={inputClass}
          value={valor.procesoId}
          onChange={(evento) => {
            const procesoId = evento.target.value;
            const mantiene = procesoPermiteFiltroProyecto(procesoId, procesos);
            onChange({
              ...valor,
              procesoId,
              proyecto: mantiene ? valor.proyecto : "",
            });
          }}
        >
          <option value="">Todos los procesos</option>
          {procesos.map((proceso) => (
            <option key={proceso.proceso_id} value={proceso.proceso_id}>
              {proceso.nombre}
            </option>
          ))}
        </select>
      </Field>

      {esProyectos ? (
        <Field etiqueta="Proyecto">
          <select
            className={inputClass}
            value={valor.proyecto}
            onChange={(evento) => onChange({ ...valor, proyecto: evento.target.value })}
          >
            <option value="">Todos los proyectos</option>
            {proyectos.map((nombre) => (
              <option key={nombre} value={nombre}>
                {nombre}
              </option>
            ))}
          </select>
        </Field>
      ) : null}

      <Field etiqueta="Período">
          <select
            className={inputClass}
            value={valor.tipo}
            onChange={(evento) =>
              onChange({ ...valor, tipo: evento.target.value as TipoPeriodoDashboard })
            }
          >
            <option value="anual">Anual</option>
            <option value="semestral">Semestral</option>
            <option value="trimestral">Trimestral</option>
            <option value="mensual">Mensual</option>
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

        {valor.tipo === "semestral" ? (
          <Field etiqueta="Semestre">
            <select
              className={inputClass}
              value={valor.semestre}
              onChange={(evento) => onChange({ ...valor, semestre: Number(evento.target.value) })}
            >
              <option value={1}>Primer semestre (ene–jun)</option>
              <option value={2}>Segundo semestre (jul–dic)</option>
            </select>
          </Field>
        ) : null}

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

      <Field etiqueta="Buscar">
        <input
          className={inputClass}
          value={valor.buscar}
          onChange={(evento) => onChange({ ...valor, buscar: evento.target.value })}
          placeholder="Código, nombre u objetivo"
        />
      </Field>
    </Filters>
  );
}
