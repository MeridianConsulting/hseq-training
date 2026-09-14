import type { ReactNode } from "react";

export function Table({
  columnas,
  filas,
  vacio = "No hay registros para mostrar.",
}: {
  columnas: { clave: string; etiqueta: string; clase?: string }[];
  filas: ReactNode[][];
  vacio?: string;
}) {
  return (
    <div className="-mx-3 overflow-x-auto rounded-none border-y border-slate-200 sm:mx-0 sm:rounded-xl sm:border">
      <table className="min-w-[40rem] w-full divide-y divide-slate-200 text-left text-sm">
        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            {columnas.map((col) => (
              <th key={col.clave} className={`whitespace-nowrap px-3 py-2.5 font-medium sm:px-4 sm:py-3 ${col.clase ?? ""}`}>
                {col.etiqueta}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 bg-white">
          {filas.length === 0 ? (
            <tr>
              <td className="px-3 py-8 text-center text-slate-500 sm:px-4" colSpan={columnas.length}>
                {vacio}
              </td>
            </tr>
          ) : (
            filas.map((celdas, indice) => (
              <tr key={indice} className="hover:bg-hseq-50/40">
                {celdas.map((celda, i) => (
                  <td key={i} className="px-3 py-2.5 align-top text-slate-700 sm:px-4 sm:py-3">
                    {celda}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
