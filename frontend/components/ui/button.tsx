"use client";

import type { ButtonHTMLAttributes, ReactNode } from "react";

type Variante = "primary" | "secondary" | "ghost" | "danger";

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variante?: Variante;
  children: ReactNode;
};

const clases: Record<Variante, string> = {
  primary:
    "bg-hseq-800 text-white hover:bg-hseq-700 disabled:opacity-60",
  secondary:
    "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-60",
  ghost: "text-hseq-700 hover:bg-hseq-50 disabled:opacity-60",
  danger: "bg-red-700 text-white hover:bg-red-800 disabled:opacity-60",
};

export function Button({ variante = "primary", className = "", children, ...rest }: Props) {
  return (
    <button
      className={`inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold transition ${clases[variante]} ${className}`}
      {...rest}
    >
      {children}
    </button>
  );
}
