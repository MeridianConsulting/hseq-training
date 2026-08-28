import { Button } from "@/components/ui/button";

export function Pagination({
  pagina,
  ultima,
  onCambiar,
}: {
  pagina: number;
  ultima: number;
  onCambiar: (pagina: number) => void;
}) {
  if (ultima <= 1) {
    return null;
  }

  return (
    <div className="mt-4 flex items-center justify-end gap-2">
      <Button
        type="button"
        variante="secondary"
        disabled={pagina <= 1}
        onClick={() => onCambiar(pagina - 1)}
      >
        Anterior
      </Button>
      <span className="text-sm text-slate-500">
        Página {pagina} de {ultima}
      </span>
      <Button
        type="button"
        variante="secondary"
        disabled={pagina >= ultima}
        onClick={() => onCambiar(pagina + 1)}
      >
        Siguiente
      </Button>
    </div>
  );
}
