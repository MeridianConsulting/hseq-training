"use client";

import { FormEvent, useEffect, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { Eye, EyeOff, LogIn } from "lucide-react";
import { useAuth } from "@/components/auth-provider";
import { Button } from "@/components/ui/button";

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
    <main className="relative flex min-h-screen items-center justify-center bg-hseq-950 px-4 py-10 sm:px-6">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(45,212,191,0.22),transparent_36%),radial-gradient(circle_at_80%_80%,rgba(14,116,144,0.35),transparent_42%)]" />

      <div className="relative flex w-full max-w-5xl flex-col items-center gap-8 lg:flex-row lg:items-stretch lg:justify-center lg:gap-10">
        <div className="relative flex w-full max-w-md flex-col justify-center text-center text-white lg:text-left">
          <Image
            src="/logo_principal.png"
            alt="Meridian Consulting"
            width={364}
            height={73}
            preload
            className="mx-auto mb-6 h-[3.9rem] w-auto object-contain sm:h-[4.55rem] lg:absolute lg:top-[10%] lg:right-[20%] lg:mx-0 lg:mb-0"
            style={{ width: "auto" }}
          />
          <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
            Programa de capacitación y entrenamiento HSEQ
          </h2>
          <p className="mt-5 text-sm leading-6 text-hseq-100/90">
            Acceda con su usuario o correo institucional para gestionar
            asignaciones, cumplimientos y el plan anual de capacitación.
          </p>
          <p className="mt-4 text-sm text-hseq-400">Sistema de gestión HSEQ</p>
        </div>

        <div className="flex min-h-[28rem] w-full max-w-lg flex-col justify-center rounded-2xl border border-white/10 bg-white px-8 py-12 shadow-xl sm:min-h-[32rem] sm:px-10 sm:py-14">
          <h1 className="text-2xl font-semibold text-hseq-900">Iniciar sesión</h1>
          <p className="mt-2 text-sm text-slate-500">
            Use su correo o nombre de usuario y su contraseña.
          </p>

          <form className="mt-10 flex flex-1 flex-col justify-center space-y-7" onSubmit={onSubmit}>
            <label className="block">
              <span className="mb-2 block text-sm font-medium text-slate-700">
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
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm outline-none transition focus:border-hseq-600 focus:ring-2 focus:ring-hseq-100"
                placeholder="usuario o correo@empresa.com"
              />
            </label>

            <label className="block">
              <span className="mb-2 block text-sm font-medium text-slate-700">
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
                  className="w-full rounded-lg border border-slate-300 bg-white px-3 py-3 pr-11 text-sm outline-none transition focus:border-hseq-600 focus:ring-2 focus:ring-hseq-100"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  onClick={() => setMostrarPassword((valor) => !valor)}
                  className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 transition hover:text-hseq-800"
                  aria-label={mostrarPassword ? "Ocultar contraseña" : "Mostrar contraseña"}
                  tabIndex={-1}
                >
                  {mostrarPassword ? (
                    <EyeOff className="h-4 w-4" aria-hidden />
                  ) : (
                    <Eye className="h-4 w-4" aria-hidden />
                  )}
                </button>
              </div>
            </label>

            {error ? (
              <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                {error}
              </p>
            ) : null}

            <Button
              type="submit"
              disabled={enviando || !listo}
              className="mt-auto w-full py-3"
            >
              <LogIn className="h-4 w-4" aria-hidden />
              {enviando ? "Ingresando..." : "Ingresar"}
            </Button>
          </form>
        </div>
      </div>
    </main>
  );
}
