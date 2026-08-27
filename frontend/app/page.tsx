"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/components/auth-provider";

export default function HomePage() {
  const router = useRouter();
  const { usuario, listo, logout } = useAuth();

  useEffect(() => {
    if (listo && !usuario) {
      router.replace("/login");
    }
  }, [listo, usuario, router]);

  if (!listo || !usuario) {
    return (
      <main className="flex min-h-screen items-center justify-center text-sm text-slate-500">
        Cargando sesión...
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <div>
            <p className="text-xs font-medium tracking-[0.16em] text-hseq-600 uppercase">
              HSEQ Capacitaciones
            </p>
            <h1 className="text-lg font-semibold text-hseq-900">Inicio</h1>
          </div>
          <button
            type="button"
            onClick={async () => {
              await logout();
              router.replace("/login");
            }}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Cerrar sesión
          </button>
        </div>
      </header>

      <section className="mx-auto max-w-5xl px-6 py-10">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-xl font-semibold text-hseq-900">
            Bienvenido, {usuario.nombre_completo}
          </h2>
          <p className="mt-2 text-sm text-slate-500">
            Sesión iniciada como <span className="font-medium text-slate-700">{usuario.nombre_usuario}</span>
            {" · "}
            {usuario.correo}
          </p>
          <dl className="mt-6 grid gap-4 sm:grid-cols-2">
            <div className="rounded-xl bg-hseq-50 px-4 py-3">
              <dt className="text-xs tracking-wide text-hseq-700 uppercase">Rol de sistema</dt>
              <dd className="mt-1 text-sm font-medium text-hseq-900">{usuario.rol}</dd>
            </div>
            <div className="rounded-xl bg-slate-50 px-4 py-3">
              <dt className="text-xs tracking-wide text-slate-500 uppercase">Roles HSEQ</dt>
              <dd className="mt-1 text-sm font-medium text-slate-800">
                {usuario.roles.length > 0
                  ? usuario.roles.map((rol) => rol.nombre).join(", ")
                  : "Sin roles asignados"}
              </dd>
            </div>
          </dl>
        </div>
      </section>
    </main>
  );
}
