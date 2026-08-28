import type { ReactNode } from "react";

type Tono = "info" | "ok" | "aviso" | "error";

const clases: Record<Tono, string> = {
  info: "border-hseq-100 bg-hseq-50 text-hseq-900",
  ok: "border-emerald-200 bg-emerald-50 text-emerald-800",
  aviso: "border-amber-200 bg-amber-50 text-amber-800",
  error: "border-red-200 bg-red-50 text-red-700",
};

export function Alert({
  children,
  tono = "info",
}: {
  children: ReactNode;
  tono?: Tono;
}) {
  return (
    <div className={`rounded-lg border px-3 py-2 text-sm ${clases[tono]}`} role="status">
      {children}
    </div>
  );
}
