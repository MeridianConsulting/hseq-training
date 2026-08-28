"use client";

import { FormEvent, useEffect, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useAuth } from "@/components/auth-provider";

export default function LoginPage() {
  const router = useRouter();
  const { usuario, listo, login } = useAuth();
  const [identificador, setIdentificador] = useState("");
  const [password, setPassword] = useState("");
  const [mostrarPassword, setMostrarPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    if (listo && usuario) {
      router.replace("/");
    }
  }, [listo, usuario, router]);

  async function onSubmit(evento: FormEvent<HTMLFormElement>) {
    evento.preventDefault();
    setError(null);
    setEnviando(true);

    const mensaje = await login(identificador.trim(), password);
    setEnviando(false);

    if (mensaje) {
      setError(mensaje);
      return;
    }

    router.replace("/");
  }

  return (
    <main className="min-h-screen lg:grid lg:grid-cols-2">
      <section className="relative hidden overflow-hidden bg-hseq-950 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(45,212,191,0.22),transparent_36%),radial-gradient(circle_at_80%_80%,rgba(14,116,144,0.35),transparent_42%)]" />
        <div className="relative">
          <Image
            src="/logo_principal.png"
            alt="Meridian Consulting"
            width={280}
            height={56}
            preload
            className="h-14 max-w-[280px] object-contain object-left"
            style={{ width: "auto" }}
          />
          <h1 className="mt-10 max-w-md text-4xl font-semibold tracking-tight">
            Programa de capacitación y entrenamiento HSEQ
          </h1>
        </div>
        <div className="relative max-w-md space-y-4 text-sm leading-6 text-hseq-100/90">
          <p>
            Acceda con su usuario o correo institucional para gestionar
            asignaciones, cumplimientos y el plan anual de capacitación.
          </p>
          <p className="text-hseq-400">Sistema de gestión HSEQ</p>
        </div>
      </section>

      <section className="flex items-center justify-center px-6 py-12 sm:px-10">
        <div className="w-full max-w-md">
          <div className="mb-8 lg:hidden">
            <div className="mb-6 flex justify-center rounded-xl bg-hseq-950 px-6 py-5">
              <Image
                src="/logo_principal.png"
                alt="Meridian Consulting"
                width={240}
                height={48}
                className="h-12 max-w-[240px] object-contain"
                style={{ width: "auto" }}
              />
            </div>
            <h1 className="text-2xl font-semibold text-hseq-900">Iniciar sesión</h1>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <div className="hidden lg:block">
              <h2 className="text-2xl font-semibold text-hseq-900">Iniciar sesión</h2>
              <p className="mt-2 text-sm text-slate-500">
                Use su correo o nombre de usuario y su contraseña.
              </p>
            </div>

            <form className="mt-8 space-y-5 lg:mt-6" onSubmit={onSubmit}>
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-slate-700">
                  Usuario o correo
                </span>
                <input
                  type="text"
                  name="usuario"
                  autoComplete="username"
                  required
                  minLength={3}
                  value={identificador}
                  onChange={(evento) => setIdentificador(evento.target.value)}
                  className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-hseq-600 focus:ring-2 focus:ring-hseq-100"
                  placeholder="usuario o correo@empresa.com"
                />
              </label>

              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-slate-700">
                  Contraseña
                </span>
                <div className="relative">
                  <input
                    type={mostrarPassword ? "text" : "password"}
                    name="password"
                    autoComplete="current-password"
                    required
                    value={password}
                    onChange={(evento) => setPassword(evento.target.value)}
                    className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 pr-24 text-sm outline-none transition focus:border-hseq-600 focus:ring-2 focus:ring-hseq-100"
                    placeholder="••••••••"
                  />
                  <button
                    type="button"
                    onClick={() => setMostrarPassword((valor) => !valor)}
                    className="absolute inset-y-0 right-2 my-auto rounded-md px-2 text-xs font-medium text-hseq-700 hover:bg-hseq-50"
                  >
                    {mostrarPassword ? "Ocultar" : "Mostrar"}
                  </button>
                </div>
              </label>

              {error ? (
                <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                  {error}
                </p>
              ) : null}

              <button
                type="submit"
                disabled={enviando || !listo}
                className="w-full rounded-lg bg-hseq-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-hseq-700 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {enviando ? "Ingresando..." : "Ingresar"}
              </button>
            </form>
          </div>
        </div>
      </section>
    </main>
  );
}
