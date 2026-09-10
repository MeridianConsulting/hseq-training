import type { ItemCatalogo } from "@/lib/tipos";

export function nombreProcesoRequiereProyecto(nombre: string | null | undefined): boolean {
  const n = (nombre ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
  if (n === "proyectos") {
    return true;
  }
  return n.includes("gestion de proyectos");
}

export function procesoRequiereProyecto(
  procesoId: string | number | null | undefined,
  procesos: { proceso_id: number; nombre: string }[],
): boolean {
  if (procesoId === null || procesoId === undefined || procesoId === "") {
    return false;
  }
  const seleccionado = procesos.find((item) => String(item.proceso_id) === String(procesoId));
  return nombreProcesoRequiereProyecto(seleccionado?.nombre);
}

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

  const etiqueta = nombre && nombre.trim() !== "" ? `${humanizarNombreUnidad(nombre)} (inactivo)` : `Registro ${id} (inactivo)`;

  return [...items, { [pk]: id, nombre: etiqueta }];
}

const ETIQUETAS_UNIDAD: Record<string, { singular: string; plural: string }> = {
  DIAS: { singular: "día", plural: "días" },
  MESES: { singular: "mes", plural: "meses" },
  ANIOS: { singular: "año", plural: "años" },
};

/** El ENUM interno es ANIOS; en pantalla se muestra AÑO/AÑOS. */
export function humanizarNombreUnidad(texto: string | null | undefined): string {
  if (texto == null || texto.trim() === "") {
    return "";
  }
  return texto.replace(/\bANIOS\b/gi, "AÑOS").replace(/\bANIO\b/gi, "AÑO");
}

export function etiquetaUnidad(unidad: string, cantidad?: number): string {
  const info = ETIQUETAS_UNIDAD[unidad.toUpperCase()];
  if (!info) {
    return humanizarNombreUnidad(unidad) || unidad;
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
