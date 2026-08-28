"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/components/auth-provider";
import { MENU } from "@/lib/navegacion";

export function Sidebar() {
  const pathname = usePathname();
  const { puede } = useAuth();

  return (
    <aside className="flex w-64 shrink-0 flex-col bg-hseq-950 text-white">
      <div className="border-b border-white/10 px-5 py-5">
        <p className="text-xs uppercase tracking-[0.2em] text-hseq-400">HSEQ</p>
        <p className="mt-1 text-sm font-semibold">Capacitaciones</p>
      </div>
      <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        {MENU.map((grupo) => {
          const entradas = grupo.entradas.filter((entrada) => puede(entrada.permiso));
          if (entradas.length === 0) {
            return null;
          }

          return (
            <div key={grupo.titulo}>
              <p className="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-hseq-400">
                {grupo.titulo}
              </p>
              <ul className="space-y-1">
                {entradas.map((entrada) => {
                  const activa = pathname === entrada.ruta || pathname.startsWith(`${entrada.ruta}/`);
                  return (
                    <li key={entrada.ruta}>
                      <Link
                        href={entrada.ruta}
                        className={`flex items-center justify-between rounded-lg px-3 py-2 text-sm transition ${
                          activa
                            ? "bg-white/10 text-white"
                            : "text-hseq-100/80 hover:bg-white/5 hover:text-white"
                        }`}
                      >
                        <span>{entrada.etiqueta}</span>
                        {entrada.preparado ? (
                          <span className="text-[10px] uppercase tracking-wide text-hseq-400">Pronto</span>
                        ) : null}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          );
        })}
      </nav>
    </aside>
  );
}
