import type { ReactNode } from "react";

export function Table({
  columnas,
  filas,
  vacio = "No hay registros para mostrar.",
  compacta = false,
}: {
  columnas: { clave: string; etiqueta: string; clase?: string }[];
  filas: ReactNode[][];
  vacio?: string;
  /** Menos padding y filas más bajas (listados densos). */
  compacta?: boolean;
}) {
  const padTh = compacta
    ? "px-2 py-1.5 text-[11px]"
    : "px-3 py-2.5 sm:px-4 sm:py-3";
  const padTd = compacta
    ? "px-2 py-1.5 align-middle"
    : "px-3 py-2.5 align-top sm:px-4 sm:py-3";

  return (
    <div className="-mx-3 overflow-x-auto rounded-none border-y border-slate-200 sm:mx-0 sm:rounded-xl sm:border">
      <table className="min-w-[40rem] w-full divide-y divide-slate-200 text-left text-sm">
        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            {columnas.map((col) => (
              <th key={col.clave} className={`whitespace-nowrap font-medium ${padTh} ${col.clase ?? ""}`}>
                {col.etiqueta}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 bg-white">
          {filas.length === 0 ? (
            <tr>
              <td
                className={`py-8 text-center text-slate-500 ${compacta ? "px-2" : "px-3 sm:px-4"}`}
                colSpan={columnas.length}
              >
                {vacio}
              </td>
            </tr>
          ) : (
            filas.map((celdas, indice) => (
              <tr key={indice} className="hover:bg-hseq-50/40">
                {celdas.map((celda, i) => (
                  <td key={i} className={`${padTd} text-slate-700 ${columnas[i]?.clase ?? ""}`}>
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
