import type { ReactNode } from "react";

export function PageHeader({
  titulo,
  descripcion,
  acciones,
}: {
  titulo: string;
  descripcion?: string;
  acciones?: ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-hseq-900">{titulo}</h1>
        {descripcion ? <p className="mt-1 text-sm text-slate-500">{descripcion}</p> : null}
      </div>
      {acciones ? <div className="flex flex-wrap gap-2">{acciones}</div> : null}
    </div>
  );
}
