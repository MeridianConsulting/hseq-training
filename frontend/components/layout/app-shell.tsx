"use client";

import Image from "next/image";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { LogOut, Menu } from "lucide-react";
import { useAuth } from "@/components/auth-provider";
import { Sidebar } from "@/components/layout/sidebar";
import { Button } from "@/components/ui/button";

import type { ReactNode } from "react";

export function AppShell({ children }: { children: ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { usuario, listo, autenticado, logout } = useAuth();
  const [menuAbierto, setMenuAbierto] = useState(false);

  useEffect(() => {
    if (listo && !autenticado) {
      router.replace("/login");
    }
  }, [listo, autenticado, router]);

  useEffect(() => {
    setMenuAbierto(false);
  }, [pathname]);

  useEffect(() => {
    if (!menuAbierto) {
      return;
    }
    const anterior = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKey = (evento: KeyboardEvent) => {
      if (evento.key === "Escape") {
        setMenuAbierto(false);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = anterior;
      window.removeEventListener("keydown", onKey);
    };
  }, [menuAbierto]);

  if (!listo || !autenticado) {
    return (
      <main className="flex min-h-screen items-center justify-center text-sm text-slate-500">
        Cargando...
      </main>
    );
  }

  return (
    <div className="flex min-h-screen">
      <Sidebar abierto={menuAbierto} onCerrar={() => setMenuAbierto(false)} />
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex items-center justify-between gap-2 border-b border-slate-200 bg-white px-3 py-2.5 sm:gap-4 sm:px-4 lg:px-6 lg:py-3">
          <div className="flex min-w-0 items-center gap-2 sm:gap-3">
            <button
              type="button"
              className="rounded-md p-1.5 text-hseq-900 hover:bg-slate-100 lg:hidden"
              aria-label="Abrir menú"
              aria-expanded={menuAbierto}
              onClick={() => setMenuAbierto(true)}
            >
              <Menu className="h-5 w-5" aria-hidden />
            </button>
            <div className="flex items-center rounded-lg bg-hseq-950 px-2 py-1 sm:px-3 sm:py-1.5">
              <Image
                src="/logo_principal.png"
                alt="Meridian Consulting"
                width={160}
                height={32}
                className="h-6 w-auto object-contain sm:h-8"
                style={{ width: "auto" }}
              />
            </div>
          </div>
          <div className="flex min-w-0 items-center gap-2 sm:gap-3">
            <div className="hidden text-right min-[420px]:block">
              <p className="max-w-[9rem] truncate text-sm font-medium text-hseq-900 sm:max-w-[14rem]">
                {usuario?.nombre_usuario}
              </p>
              <p className="hidden max-w-[14rem] truncate text-xs text-slate-500 md:block">
                {usuario?.correo}
              </p>
            </div>
            <Button
              type="button"
              variante="secondary"
              className="shrink-0 px-2.5 sm:px-4"
              onClick={() => {
                void logout().then(() => router.replace("/login"));
              }}
            >
              <LogOut className="h-4 w-4" aria-hidden />
              <span className="hidden sm:inline">Salir</span>
            </Button>
          </div>
        </header>
        <main className="min-w-0 flex-1 px-3 py-4 sm:px-4 sm:py-5 lg:px-6 lg:py-6">{children}</main>
      </div>
    </div>
  );
}
