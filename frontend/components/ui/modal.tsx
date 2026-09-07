import type { ReactNode } from "react";

export function Modal({
  abierto,
  titulo,
  onCerrar,
  children,
  amplio = false,
}: {
  abierto: boolean;
  titulo: string;
  onCerrar: () => void;
  children: ReactNode;
  amplio?: boolean;
}) {
  if (!abierto) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-hseq-950/50 p-4 sm:p-8">
      <div className={`w-full rounded-2xl bg-white p-6 shadow-xl ${amplio ? "max-w-5xl" : "max-w-3xl"}`}>
        <div className="mb-4 flex items-start justify-between gap-4">
          <h2 className="text-lg font-semibold text-hseq-900">{titulo}</h2>
          <button
            type="button"
            onClick={onCerrar}
            className="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100"
          >
            Cerrar
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
