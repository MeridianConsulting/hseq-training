"use client";

import { useEffect, useRef, type ReactNode } from "react";

/** Pila de modales abiertos: Escape cierra solo el de más arriba. */
const pila: symbol[] = [];

export function Modal({
  abierto,
  titulo,
  onCerrar,
  children,
  amplio = false,
  completo = false,
}: {
  abierto: boolean;
  titulo: string;
  onCerrar: () => void;
  children: ReactNode;
  amplio?: boolean;
  /** Casi todo el ancho de la pantalla (tablas con muchas columnas). */
  completo?: boolean;
}) {
  const onCerrarRef = useRef(onCerrar);
  useEffect(() => {
    onCerrarRef.current = onCerrar;
  });

  useEffect(() => {
    if (!abierto) {
      return;
    }
    const id = Symbol("modal");
    pila.push(id);
    function alPresionar(evento: KeyboardEvent) {
      if (evento.key === "Escape" && pila[pila.length - 1] === id) {
        evento.stopPropagation();
        onCerrarRef.current();
      }
    }
    document.addEventListener("keydown", alPresionar);
    return () => {
      document.removeEventListener("keydown", alPresionar);
      const i = pila.indexOf(id);
      if (i >= 0) pila.splice(i, 1);
    };
  }, [abierto]);

  if (!abierto) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-hseq-950/50 sm:items-start sm:p-8">
      <div
        className={`w-full rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6 ${
          completo ? "max-w-[min(96vw,110rem)]" : amplio ? "max-w-5xl" : "max-w-3xl"
        } max-h-[96dvh] overflow-y-auto`}
      >
        <div className="mb-4 flex items-start justify-between gap-3">
          <h2 className="text-base font-semibold text-hseq-900 sm:text-lg">{titulo}</h2>
          <button
            type="button"
            onClick={onCerrar}
            className="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100"
          >
            Cerrar
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
