"use client";

import { useState } from "react";
import { TarjetaCronograma } from "@/components/cronograma/tarjeta-cronograma";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import type { MesCronograma } from "@/lib/tipos";

const PAGINA = 5;

export function BloqueMesCronograma({
  bloque,
  desplegable,
  abierto,
  onToggle,
}: {
  bloque: MesCronograma;
  desplegable: boolean;
  abierto: boolean;
  onToggle?: () => void;
}) {
  const [visibles, setVisibles] = useState(PAGINA);
  const mostrarLista = !desplegable || abierto;
  const items = bloque.items.slice(0, visibles);
  const hayMas = visibles < bloque.items.length;

  return (
    <Card>
      {desplegable ? (
        <button
          type="button"
          className="flex w-full items-center justify-between gap-3 text-left"
          onClick={onToggle}
          aria-expanded={abierto}
        >
          <span>
            <span className="text-sm font-semibold text-hseq-900">{bloque.nombre}</span>
            <span className="mt-1 block text-sm text-slate-500">
              {bloque.total === 0
                ? "Sin capacitaciones programadas"
                : `${bloque.total} capacitación${bloque.total === 1 ? "" : "es"} programada${bloque.total === 1 ? "" : "s"}`}
            </span>
          </span>
          <span className="text-sm font-medium text-hseq-700">
            {abierto ? "Ocultar" : "Mostrar capacitaciones"}
          </span>
        </button>
      ) : (
        <div>
          <h2 className="text-sm font-semibold text-hseq-900">{bloque.nombre}</h2>
          <p className="mt-1 text-sm text-slate-500">
            {bloque.total === 0
              ? "Sin capacitaciones programadas"
              : `${bloque.total} capacitación${bloque.total === 1 ? "" : "es"} programada${bloque.total === 1 ? "" : "s"}`}
          </p>
        </div>
      )}

      {mostrarLista ? (
        bloque.total === 0 ? (
          desplegable ? (
            <p className="mt-4 text-sm text-slate-500">No hay capacitaciones programadas en este mes.</p>
          ) : null
        ) : (
          <div className="mt-4 space-y-3">
            {items.map((item) => (
              <TarjetaCronograma key={item.plan_detalle_id} item={item} />
            ))}
            <p className="text-xs text-slate-500">
              Mostrando {items.length} de {bloque.total} capacitación
              {bloque.total === 1 ? "" : "es"}
            </p>
            {hayMas ? (
              <Button type="button" variante="secondary" onClick={() => setVisibles((n) => n + PAGINA)}>
                Ver más
              </Button>
            ) : null}
          </div>
        )
      ) : null}
    </Card>
  );
}
