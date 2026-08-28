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
    <div className="overflow-x-auto rounded-xl border border-slate-200">
      <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            {columnas.map((col) => (
              <th key={col.clave} className={`px-4 py-3 font-medium ${col.clase ?? ""}`}>
                {col.etiqueta}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 bg-white">
          {filas.length === 0 ? (
            <tr>
              <td className="px-4 py-8 text-center text-slate-500" colSpan={columnas.length}>
                {vacio}
              </td>
            </tr>
          ) : (
            filas.map((celdas, indice) => (
              <tr key={indice} className="hover:bg-hseq-50/40">
                {celdas.map((celda, i) => (
                  <td key={i} className="px-4 py-3 align-top text-slate-700">
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
