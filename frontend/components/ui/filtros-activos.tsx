"use client";

import { Button } from "@/components/ui/button";
import { X } from "lucide-react";
import type { ReactNode } from "react";

export type ChipFiltro = {
  clave: string;
  etiqueta: string;
  valor: string;
};

export function FiltrosActivos({
  chips,
  onQuitar,
  onLimpiar,
}: {
  chips: ChipFiltro[];
  onQuitar: (clave: string) => void;
  onLimpiar?: () => void;
}) {
  if (chips.length === 0) {
    return null;
  }

  return (
    <div className="mb-4 flex flex-wrap items-center gap-2">
      <span className="text-xs font-medium uppercase tracking-wide text-slate-500">Activos:</span>
      {chips.map((chip) => (
        <button
          key={chip.clave}
          type="button"
          onClick={() => onQuitar(chip.clave)}
          className="inline-flex items-center gap-1 rounded-full bg-hseq-50 px-2.5 py-1 text-xs font-medium text-hseq-900 ring-1 ring-hseq-200 hover:bg-hseq-100"
        >
          {chip.etiqueta}: {chip.valor}
          <X className="h-3 w-3" aria-hidden />
          <span className="sr-only">Quitar filtro {chip.etiqueta}</span>
        </button>
      ))}
      {onLimpiar ? (
        <Button type="button" variante="ghost" onClick={onLimpiar} className="h-7 px-2 text-xs">
          Limpiar filtros
        </Button>
      ) : null}
    </div>
  );
}

export function FiltersBar({
  children,
  acciones,
}: {
  children: ReactNode;
  acciones?: ReactNode;
}) {
  return (
    <div className="mb-4 space-y-3">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{children}</div>
      {acciones ? <div className="flex flex-wrap items-end gap-2">{acciones}</div> : null}
    </div>
  );
}

export function ListaCargando({ mensaje = "Cargando…" }: { mensaje?: string }) {
  return (
    <div className="mb-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
      {mensaje}
    </div>
  );
}
