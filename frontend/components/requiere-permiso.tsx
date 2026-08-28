"use client";

import type { ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { useAuth } from "@/components/auth-provider";

export function RequierePermiso({
  permiso,
  children,
}: {
  permiso: string;
  children: ReactNode;
}) {
  const router = useRouter();
  const { listo, autenticado, puede } = useAuth();
  const autorizado = puede(permiso);

  useEffect(() => {
    if (listo && autenticado && !autorizado) {
      router.replace("/sin-acceso");
    }
  }, [listo, autenticado, autorizado, router]);

  if (!listo || !autenticado || !autorizado) {
    return (
      <p className="text-sm text-slate-500">Verificando acceso...</p>
    );
  }

  return <>{children}</>;
}
