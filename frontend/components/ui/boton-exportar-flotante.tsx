"use client";

import type { ButtonHTMLAttributes, ReactNode } from "react";
import { Button } from "@/components/ui/button";

type Props = Omit<ButtonHTMLAttributes<HTMLButtonElement>, "className" | "children"> & {
  children: ReactNode;
};

/** Botón de exportar fijo abajo a la derecha (mismo estilo en todo el sistema). */
export function BotonExportarFlotante({ children, ...rest }: Props) {
  return (
    <div className="btn-export-fab-wrap">
      <Button className="btn-export-fab" {...rest}>
        {children}
      </Button>
    </div>
  );
}
