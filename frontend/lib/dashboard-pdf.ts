import { jsPDF } from "jspdf";
import type {
  KpiCumplimiento,
  KpiEficacia,
  KpiHoras,
  KpiSoportes,
  ResumenDashboard,
} from "@/lib/tipos";

const MARGIN = 14;
const PAGE_W = 210;
const PAGE_H = 297;
const CONTENT_W = PAGE_W - MARGIN * 2;
const COLOR_PROGRAMADO: [number, number, number] = [14, 116, 144];
const COLOR_EJECUTADO: [number, number, number] = [20, 184, 166];
const COLOR_TEXTO: [number, number, number] = [15, 23, 42];
const COLOR_MUTED: [number, number, number] = [100, 116, 139];
const COLOR_LINEA: [number, number, number] = [226, 232, 240];

export type MetaDashboardPdf = {
  procesoEtiqueta: string;
  proyectoEtiqueta: string | null;
};

function num(n: number, decimales = 0): string {
  return new Intl.NumberFormat("es-CO", {
    minimumFractionDigits: decimales,
    maximumFractionDigits: decimales,
  }).format(n);
}

function pct(valor: number | null, sinProgramado = false): string {
  if (sinProgramado) return "Sin programado";
  if (valor === null) return "—";
  return `${num(valor, 1)} %`;
}

function fechaHoy(): string {
  return new Intl.DateTimeFormat("es-CO", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date());
}

/** Helvetica no cubre tildes; normaliza texto dinamico del API. */
function pdfSafe(valor: string): string {
  return valor.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

function slugArchivo(periodo: string): string {
  const base = pdfSafe(periodo)
    .replace(/[^a-zA-Z0-9]+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 40);
  const dia = new Date().toISOString().slice(0, 10);
  return `panel-control_${base || "periodo"}_${dia}.pdf`;
}

class PdfBuilder {
  doc: jsPDF;
  y: number;

  constructor() {
    this.doc = new jsPDF({ unit: "mm", format: "a4" });
    this.y = MARGIN;
  }

  asegurarEspacio(alto: number): void {
    if (this.y + alto <= PAGE_H - MARGIN) return;
    this.doc.addPage();
    this.y = MARGIN;
  }

  texto(
    valor: string,
    x: number,
    size: number,
    opts?: { bold?: boolean; color?: [number, number, number]; align?: "left" | "center" | "right" },
  ): void {
    this.doc.setFont("helvetica", opts?.bold ? "bold" : "normal");
    this.doc.setFontSize(size);
    this.doc.setTextColor(...(opts?.color ?? COLOR_TEXTO));
    this.doc.text(valor, x, this.y, { align: opts?.align ?? "left" });
  }

  linea(): void {
    this.doc.setDrawColor(...COLOR_LINEA);
    this.doc.setLineWidth(0.3);
    this.doc.line(MARGIN, this.y, PAGE_W - MARGIN, this.y);
    this.y += 4;
  }

  seccion(titulo: string): void {
    this.asegurarEspacio(12);
    this.y += 2;
    this.texto(titulo.toUpperCase(), MARGIN, 11, { bold: true, color: COLOR_PROGRAMADO });
    this.y += 5;
    this.linea();
  }

  parrafoMeta(lineas: string[]): void {
    for (const linea of lineas) {
      this.asegurarEspacio(6);
      this.texto(linea, MARGIN, 9, { color: COLOR_MUTED });
      this.y += 5;
    }
  }

  /** Dos barras verticales programado / ejecutado. */
  barrasCumplimiento(kpi: KpiCumplimiento, titulo: string, x: number, anchoCol: number): void {
    const top = this.y;
    this.doc.setFont("helvetica", "bold");
    this.doc.setFontSize(9);
    this.doc.setTextColor(...COLOR_TEXTO);
    this.doc.text(titulo, x, top, { maxWidth: anchoCol - 2 });

    const chartTop = top + 8;
    const chartH = 28;
    const barW = 10;
    const gap = 8;
    const maximo = Math.max(kpi.programado, kpi.ejecutado, 1);
    const hProg = (kpi.programado / maximo) * chartH;
    const hEjec = (kpi.ejecutado / maximo) * chartH;
    const baseY = chartTop + chartH;
    const centro = x + anchoCol / 2;
    const xProg = centro - gap / 2 - barW;
    const xEjec = centro + gap / 2;

    this.doc.setFillColor(...COLOR_PROGRAMADO);
    this.doc.rect(xProg, baseY - hProg, barW, hProg, "F");
    this.doc.setFillColor(...COLOR_EJECUTADO);
    this.doc.rect(xEjec, baseY - hEjec, barW, hEjec, "F");

    this.doc.setFont("helvetica", "normal");
    this.doc.setFontSize(7);
    this.doc.setTextColor(...COLOR_MUTED);
    this.doc.text(String(kpi.programado), xProg + barW / 2, baseY - hProg - 2, { align: "center" });
    this.doc.text(String(kpi.ejecutado), xEjec + barW / 2, baseY - hEjec - 2, { align: "center" });
    this.doc.text("Prog.", xProg + barW / 2, baseY + 4, { align: "center" });
    this.doc.text("Ejec.", xEjec + barW / 2, baseY + 4, { align: "center" });

    this.doc.setFont("helvetica", "bold");
    this.doc.setFontSize(8);
    this.doc.setTextColor(...COLOR_PROGRAMADO);
    this.doc.text(pct(kpi.porcentaje, kpi.sin_programado), x + anchoCol / 2, baseY + 10, {
      align: "center",
    });
  }

  filaKpisTres(
    items: { titulo: string; kpi: KpiCumplimiento }[],
  ): void {
    this.asegurarEspacio(55);
    const colW = CONTENT_W / 3;
    const yInicio = this.y;
    items.forEach((item, i) => {
      this.y = yInicio;
      this.barrasCumplimiento(item.kpi, item.titulo, MARGIN + i * colW, colW);
    });
    this.y = yInicio + 52;
  }

  barraHorizontal(
    etiqueta: string,
    valorIzq: string,
    valorDer: string,
    ratio: number,
    color: [number, number, number],
  ): void {
    this.asegurarEspacio(14);
    this.texto(etiqueta, MARGIN, 9, { bold: true });
    this.y += 4;
    this.doc.setFont("helvetica", "normal");
    this.doc.setFontSize(8);
    this.doc.setTextColor(...COLOR_MUTED);
    this.doc.text(valorIzq, MARGIN, this.y);
    this.doc.text(valorDer, PAGE_W - MARGIN, this.y, { align: "right" });
    this.y += 2;
    const trackH = 4;
    const trackW = CONTENT_W;
    this.doc.setFillColor(241, 245, 249);
    this.doc.roundedRect(MARGIN, this.y, trackW, trackH, 1, 1, "F");
    const fill = Math.max(0, Math.min(1, ratio)) * trackW;
    if (fill > 0) {
      this.doc.setFillColor(...color);
      this.doc.roundedRect(MARGIN, this.y, fill, trackH, 1, 1, "F");
    }
    this.y += 10;
  }

  kv(etiqueta: string, valor: string): void {
    this.asegurarEspacio(6);
    this.doc.setFont("helvetica", "normal");
    this.doc.setFontSize(9);
    this.doc.setTextColor(...COLOR_MUTED);
    this.doc.text(etiqueta, MARGIN, this.y);
    this.doc.setFont("helvetica", "bold");
    this.doc.setTextColor(...COLOR_TEXTO);
    this.doc.text(valor, PAGE_W - MARGIN, this.y, { align: "right" });
    this.y += 6;
  }
}

function textoEficacia(kpi: KpiEficacia): { valor: string; ratio: number } {
  if (kpi.promedio === null) {
    return { valor: `Sin evaluaciones (${kpi.evaluaciones})`, ratio: 0 };
  }
  return {
    valor: `${num(kpi.promedio, 2)} / 5 · ${kpi.evaluaciones} eval.`,
    ratio: kpi.promedio / 5,
  };
}

function textoHoras(kpi: KpiHoras): { izq: string; der: string; ratio: number } {
  const max = Math.max(kpi.programadas, kpi.ejecutadas, 1);
  return {
    izq: `Prog. ${num(kpi.programadas, 1)} h`,
    der: `Ejec. ${num(kpi.ejecutadas, 1)} h`,
    ratio: kpi.ejecutadas / max,
  };
}

function textoSoportes(kpi: KpiSoportes): { izq: string; der: string; ratio: number } {
  const ratio =
    kpi.requieren > 0 ? kpi.con_soporte / kpi.requieren : kpi.porcentaje !== null ? kpi.porcentaje / 100 : 0;
  return {
    izq: `Con soporte ${kpi.con_soporte} / ${kpi.requieren}`,
    der: `Pendientes ${kpi.pendientes} · ${pct(kpi.porcentaje)}`,
    ratio,
  };
}

export function exportarDashboardPdf(resumen: ResumenDashboard, meta: MetaDashboardPdf): void {
  const b = new PdfBuilder();
  const { doc } = b;

  b.texto("Panel de control HSEQ", MARGIN, 16, { bold: true, color: COLOR_PROGRAMADO });
  b.y += 7;
  b.texto("Resumen ejecutivo del programa de capacitaciones", MARGIN, 9, { color: COLOR_MUTED });
  b.y += 6;
  b.linea();

  const alcanceProceso = meta.procesoEtiqueta || resumen.alcance.proceso || "Todos";
  const proyecto =
    meta.proyectoEtiqueta ??
    (resumen.alcance.proyecto && resumen.alcance.proyecto !== ""
      ? resumen.alcance.proyecto
      : null);

  b.parrafoMeta([
    `Periodo: ${pdfSafe(resumen.periodo.etiqueta)}`,
    `Proceso: ${pdfSafe(alcanceProceso)}`,
    `Proyecto: ${pdfSafe(proyecto ?? "Todos / no aplica")}`,
    `Generado: ${fechaHoy()}`,
  ]);
  b.y += 2;

  b.seccion("Poblacion");
  b.kv("Empleados activos", String(resumen.poblacion.activos));
  b.kv("Empleados inactivos", String(resumen.poblacion.inactivos));

  if (resumen.alertas_resumen) {
    b.seccion("Alertas (resumen)");
    b.kv("Proximas a vencer", String(resumen.alertas_resumen.proximas));
    b.kv("Vencidas", String(resumen.alertas_resumen.vencidas));
  }

  b.seccion("Cobertura");
  b.filaKpisTres([
    { titulo: "Cobertura general", kpi: resumen.cobertura.general },
    { titulo: "Induccion / reinduccion", kpi: resumen.cobertura.induccion },
    { titulo: "Tareas criticas", kpi: resumen.cobertura.tareas_criticas },
  ]);

  b.seccion("Eficacia");
  for (const [titulo, kpi] of [
    ["Eficacia general", resumen.eficacia.general],
    ["Induccion / reinduccion", resumen.eficacia.induccion],
    ["Tareas criticas", resumen.eficacia.tareas_criticas],
  ] as [string, KpiEficacia][]) {
    const t = textoEficacia(kpi);
    b.barraHorizontal(titulo, t.valor, "", t.ratio, COLOR_EJECUTADO);
  }

  b.seccion("Horas de capacitacion");
  for (const [titulo, kpi] of [
    ["Total", resumen.horas.general],
    ["Induccion / reinduccion", resumen.horas.induccion],
    ["Tareas criticas", resumen.horas.critica],
  ] as [string, KpiHoras][]) {
    const t = textoHoras(kpi);
    b.barraHorizontal(titulo, t.izq, t.der, t.ratio, COLOR_EJECUTADO);
  }

  b.seccion("Control de oportunidad y soportes");
  b.kv("Ejecutadas fuera de tiempo", String(resumen.ejecutadas_fuera_de_tiempo ?? 0));
  {
    const t = textoSoportes(resumen.soportes);
    b.barraHorizontal("Evidencias / soportes", t.izq, t.der, t.ratio, COLOR_PROGRAMADO);
  }

  const totalPaginas = doc.getNumberOfPages();
  for (let i = 1; i <= totalPaginas; i++) {
    doc.setPage(i);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(8);
    doc.setTextColor(...COLOR_MUTED);
    doc.text(`Pagina ${i} de ${totalPaginas}`, PAGE_W / 2, PAGE_H - 8, { align: "center" });
  }

  doc.save(slugArchivo(resumen.periodo.etiqueta));
}
