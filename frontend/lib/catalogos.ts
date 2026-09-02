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

const ETIQUETAS_UNIDAD: Record<string, { singular: string; plural: string }> = {
  DIAS: { singular: "día", plural: "días" },
  MESES: { singular: "mes", plural: "meses" },
  ANIOS: { singular: "año", plural: "años" },
};

export function etiquetaUnidad(unidad: string, cantidad?: number): string {
  const info = ETIQUETAS_UNIDAD[unidad];
  if (!info) {
    return unidad;
  }
  if (cantidad === undefined) {
    return info.plural;
  }
  const texto = cantidad === 1 ? info.singular : info.plural;
  return `${cantidad} ${texto}`;
}

export function detalleItemCatalogo(item: ItemCatalogo): string {
  if (item.descripcion != null && String(item.descripcion) !== "") {
    return String(item.descripcion);
  }
  if (item.cantidad != null && item.unidad != null && String(item.unidad) !== "") {
    return etiquetaUnidad(String(item.unidad), Number(item.cantidad));
  }
  if (item.unidad != null && String(item.unidad) !== "") {
    return etiquetaUnidad(String(item.unidad));
  }
  return "—";
}

export function etiquetaCampoCatalogo(campo: string): string {
  const mapa: Record<string, string> = {
    nombre: "Nombre",
    descripcion: "Descripción",
    cantidad: "Cantidad",
    unidad: "Unidad",
  };

  return mapa[campo] ?? campo.replaceAll("_", " ");
}
