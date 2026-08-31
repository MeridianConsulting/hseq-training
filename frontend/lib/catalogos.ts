import type { ItemCatalogo } from "@/lib/tipos";

/** Incluye el valor histórico inactivo para no perderlo al editar. */
export function conValorHistorico(
  items: ItemCatalogo[],
  pk: string,
  id: number | null | undefined,
  nombre: string | null | undefined,
): ItemCatalogo[] {
  if (id == null || id === 0) {
    return items;
  }

  if (items.some((item) => Number(item[pk]) === id)) {
    return items;
  }

  const etiqueta = nombre && nombre.trim() !== "" ? `${nombre} (inactivo)` : `Registro ${id} (inactivo)`;

  return [...items, { [pk]: id, nombre: etiqueta }];
}
