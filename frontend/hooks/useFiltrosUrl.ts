"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { usePathname, useRouter } from "next/navigation";

type Valores = Record<string, string>;

function leerDesdeUrl(defaults: Valores): Valores {
  if (typeof window === "undefined") {
    return { ...defaults };
  }
  const params = new URLSearchParams(window.location.search);
  const valores: Valores = { ...defaults };
  for (const clave of Object.keys(defaults)) {
    const desdeUrl = params.get(clave);
    if (desdeUrl !== null) {
      valores[clave] = desdeUrl;
    }
  }
  return valores;
}

function urlActual(): string {
  if (typeof window === "undefined") {
    return "";
  }
  return `${window.location.pathname}${window.location.search}`;
}

/**
 * Sincroniza filtros de listado con la URL (?buscar=&estado=…).
 * Los cambios de texto se debouncean antes de escribir en la URL.
 * Nunca llama router.replace dentro del updater de setState.
 */
export function useFiltrosUrl(
  defaults: Valores,
  opciones?: { debounceMs?: number; keysDebounce?: string[] },
) {
  const router = useRouter();
  const pathname = usePathname();
  const debounceMs = opciones?.debounceMs ?? 300;
  const keysDebounce = opciones?.keysDebounce ?? ["buscar"];
  const keysDebounceSet = useRef(new Set(keysDebounce));
  keysDebounceSet.current = new Set(keysDebounce);

  const [valores, setValores] = useState<Valores>(() => leerDesdeUrl(defaults));
  const valoresRef = useRef(valores);
  valoresRef.current = valores;

  const timer = useRef<number | null>(null);
  const defaultsRef = useRef(defaults);
  defaultsRef.current = defaults;

  const escribirUrl = useCallback(
    (siguiente: Valores) => {
      const params = new URLSearchParams();
      if (typeof window !== "undefined") {
        const actuales = new URLSearchParams(window.location.search);
        actuales.forEach((valor, clave) => {
          if (!(clave in defaultsRef.current)) {
            params.set(clave, valor);
          }
        });
      }
      for (const [clave, valor] of Object.entries(siguiente)) {
        const base = defaultsRef.current[clave] ?? "";
        if (valor !== "" && valor !== base) {
          params.set(clave, valor);
        }
      }
      const cadena = params.toString();
      const destino = cadena ? `${pathname}?${cadena}` : pathname;
      if (destino === urlActual()) {
        return;
      }
      router.replace(destino, { scroll: false });
    },
    [pathname, router],
  );

  const programarEscritura = useCallback(
    (siguiente: Valores, conDebounce: boolean) => {
      if (timer.current !== null) {
        window.clearTimeout(timer.current);
        timer.current = null;
      }
      if (conDebounce) {
        timer.current = window.setTimeout(() => {
          escribirUrl(siguiente);
          timer.current = null;
        }, debounceMs);
        return;
      }
      queueMicrotask(() => escribirUrl(siguiente));
    },
    [debounceMs, escribirUrl],
  );

  const setFiltro = useCallback(
    (clave: string, valor: string) => {
      if (valoresRef.current[clave] === valor) {
        return;
      }
      const siguiente = { ...valoresRef.current, [clave]: valor };
      valoresRef.current = siguiente;
      setValores(siguiente);
      programarEscritura(siguiente, keysDebounceSet.current.has(clave));
    },
    [programarEscritura],
  );

  const setVarios = useCallback(
    (parcial: Valores) => {
      const siguiente = { ...valoresRef.current, ...parcial };
      valoresRef.current = siguiente;
      setValores(siguiente);
      programarEscritura(siguiente, false);
    },
    [programarEscritura],
  );

  const limpiar = useCallback(() => {
    const base = { ...defaultsRef.current };
    valoresRef.current = base;
    setValores(base);
    programarEscritura(base, false);
  }, [programarEscritura]);

  useEffect(() => {
    return () => {
      if (timer.current !== null) {
        window.clearTimeout(timer.current);
      }
    };
  }, []);

  return { valores, setFiltro, setVarios, limpiar };
}

/** Debounce genérico para disparar cargas al cambiar dependencias. */
export function useDebouncedCallback(callback: () => void, deps: unknown[], delayMs = 300) {
  useEffect(() => {
    const id = window.setTimeout(callback, delayMs);
    return () => window.clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
}
