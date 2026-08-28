import type { ReactNode } from "react";

export function Field({
  etiqueta,
  children,
  error,
}: {
  etiqueta: string;
  children: ReactNode;
  error?: string;
}) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-medium text-slate-700">{etiqueta}</span>
      {children}
      {error ? <span className="mt-1 block text-xs text-red-600">{error}</span> : null}
    </label>
  );
}

export const inputClass =
  "w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-hseq-600 focus:ring-2 focus:ring-hseq-100";
