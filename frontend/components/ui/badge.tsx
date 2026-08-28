import type { ReactNode } from "react";

type Tono = "neutral" | "ok" | "aviso" | "alto" | "bajo";

const clases: Record<Tono, string> = {
  neutral: "bg-slate-100 text-slate-700",
  ok: "bg-emerald-100 text-emerald-800",
  aviso: "bg-amber-100 text-amber-800",
  alto: "bg-red-100 text-red-800",
  bajo: "bg-hseq-100 text-hseq-800",
};

export function Badge({
  children,
  tono = "neutral",
}: {
  children: ReactNode;
  tono?: Tono;
}) {
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${clases[tono]}`}>
      {children}
    </span>
  );
}
