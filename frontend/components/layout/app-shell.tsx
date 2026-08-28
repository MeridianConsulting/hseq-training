"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { useAuth } from "@/components/auth-provider";
import { Sidebar } from "@/components/layout/sidebar";
import { Button } from "@/components/ui/button";

import type { ReactNode } from "react";

export function AppShell({ children }: { children: ReactNode }) {
  const router = useRouter();
  const { usuario, listo, autenticado, logout } = useAuth();

  useEffect(() => {
    if (listo && !autenticado) {
      router.replace("/login");
    }
  }, [listo, autenticado, router]);

  if (!listo || !autenticado) {
    return (
      <main className="flex min-h-screen items-center justify-center text-sm text-slate-500">
        Cargando...
      </main>
    );
  }

  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-6 py-3">
          <div className="flex items-center gap-3">
            <Image
              src="/logo_principal.png"
              alt="Meridian Consulting"
              width={160}
              height={32}
              className="h-8 object-contain"
              style={{ width: "auto" }}
            />
          </div>
          <div className="flex items-center gap-3">
            <div className="text-right">
              <p className="text-sm font-medium text-hseq-900">{usuario?.nombre_usuario}</p>
              <p className="text-xs text-slate-500">{usuario?.correo}</p>
            </div>
            <Button
              type="button"
              variante="secondary"
              onClick={() => {
                void logout().then(() => router.replace("/login"));
              }}
            >
              Salir
            </Button>
          </div>
        </header>
        <main className="flex-1 px-6 py-6">{children}</main>
      </div>
    </div>
  );
}
