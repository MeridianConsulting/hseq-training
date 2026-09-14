import type { ReactNode } from "react";

export function Field({
  etiqueta,
  children,
  error,
  className,
}: {
  etiqueta: string;
  children: ReactNode;
  error?: string;
  className?: string;
}) {
  return (
    <label className={className ? `block ${className}` : "block"}>
      <span className="mb-1.5 block text-sm font-medium text-slate-700">{etiqueta}</span>
      {children}
      {error ? <span className="mt-1 block text-xs text-red-600">{error}</span> : null}
    </label>
  );
}

export const inputClass =
  "w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-hseq-600 focus:ring-2 focus:ring-hseq-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500";

export const inputClassAnio =
  "w-[5.5rem] rounded-lg border border-slate-300 bg-white px-2 py-2 text-center text-sm tabular-nums outline-none transition focus:border-hseq-600 focus:ring-2 focus:ring-hseq-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none";

export const fieldClassAnio = "w-max justify-self-start";
