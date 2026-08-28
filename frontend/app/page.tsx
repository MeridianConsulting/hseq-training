"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/components/auth-provider";
import { rutaInicial } from "@/lib/navegacion";

/**
 * Punto de entrada: envia al usuario al primer modulo al que tiene acceso o al login.
 */
export default function HomePage() {
  const router = useRouter();
  const { usuario, listo } = useAuth();

  useEffect(() => {
    if (!listo) {
      return;
    }

    router.replace(usuario ? rutaInicial(usuario.permisos ?? []) : "/login");
  }, [listo, usuario, router]);

  return (
    <main className="flex min-h-screen items-center justify-center text-sm text-slate-500">
      Cargando...
    </main>
  );
}
