"use client";

import type { ButtonHTMLAttributes, ReactNode } from "react";

type Variante = "primary" | "secondary" | "ghost" | "danger";

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variante?: Variante;
  children: ReactNode;
};

const clases: Record<Variante, string> = {
  primary: "btn-ui btn-ui-primary",
  secondary: "btn-ui btn-ui-secondary",
  ghost: "btn-ui btn-ui-ghost",
  danger: "btn-ui btn-ui-danger",
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
