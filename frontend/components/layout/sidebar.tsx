"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { LucideIcon } from "lucide-react";
import {
  BadgeCheck,
  Bell,
  CalendarDays,
  CalendarRange,
  FileSpreadsheet,
  GraduationCap,
  History,
  LayoutDashboard,
  Settings,
  ShieldCheck,
  Table2,
  Upload,
  UserPlus,
  Users,
  X,
} from "lucide-react";
import { useAuth } from "@/components/auth-provider";
import { MENU } from "@/lib/navegacion";

const ICONOS: Record<string, LucideIcon> = {
  "/dashboard": LayoutDashboard,
  "/alertas": Bell,
  "/reportes": FileSpreadsheet,
  "/capacitaciones": GraduationCap,
  "/matriz": Table2,
  "/plan-anual": CalendarRange,
  "/cronograma": CalendarDays,
  "/personal": Users,
  "/asignaciones": UserPlus,
  "/cumplimientos": BadgeCheck,
  "/catalogos": Settings,
  "/configuracion": Settings,
  "/auditoria": History,
  "/migracion": Upload,
};

export function Sidebar({
  abierto,
  onCerrar,
}: {
  abierto: boolean;
  onCerrar: () => void;
}) {
  const pathname = usePathname();
  const { puede } = useAuth();

  return (
    <>
      <button
        type="button"
        aria-label="Cerrar menú"
        className={`fixed inset-0 z-40 bg-hseq-950/50 lg:hidden ${abierto ? "" : "hidden"}`}
        onClick={onCerrar}
      />
      <aside
        className={`fixed inset-y-0 left-0 z-50 flex h-screen w-64 max-w-[min(16rem,88vw)] flex-col bg-hseq-950 text-white shadow-xl transition-transform duration-200 ease-out lg:max-w-none lg:translate-x-0 lg:shadow-none ${
          abierto ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="flex items-start justify-between border-b border-white/10 px-5 py-5">
          <div>
            <p className="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-hseq-400">
              <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
              HSEQ
            </p>
            <p className="mt-1 text-sm font-semibold">Capacitaciones</p>
          </div>
          <button
            type="button"
            onClick={onCerrar}
            className="rounded-md p-1 text-hseq-100/80 hover:bg-white/10 lg:hidden"
            aria-label="Cerrar menú"
          >
            <X className="h-5 w-5" aria-hidden />
          </button>
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
                    const Icono = ICONOS[entrada.ruta];
                    return (
                      <li key={entrada.ruta}>
                        <Link
                          href={entrada.ruta}
                          onClick={onCerrar}
                          className={`flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm transition lg:py-2 ${
                            activa
                              ? "bg-white/10 text-white"
                              : "text-hseq-100/80 hover:bg-white/5 hover:text-white"
                          }`}
                        >
                          {Icono ? <Icono className="h-4 w-4 shrink-0" aria-hidden /> : null}
                          <span className="flex-1">{entrada.etiqueta}</span>
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
      <div className="hidden w-64 shrink-0 lg:block" aria-hidden />
    </>
  );
}
