"use client";

import { FormEvent, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import {
  apiDownload,
  apiGet,
  apiPost,
  apiPostForm,
  withQuery,
  type ListaPaginada,
} from "@/lib/api";
import type { ConteoMigracion, InconsistenciaMigracion, Migracion } from "@/lib/tipos";

const anioActual = new Date().getFullYear();

export default function MigracionPage() {
  return (
    <RequierePermiso permiso="migracion.ejecutar">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [archivo, setArchivo] = useState<File | null>(null);
  const [anio, setAnio] = useState(String(anioActual));
  const [validando, setValidando] = useState(false);
  const [confirmando, setConfirmando] = useState(false);
  const [cancelando, setCancelando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [migracion, setMigracion] = useState<Migracion | null>(null);
  const [inconsistencias, setInconsistencias] = useState<InconsistenciaMigracion[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);

  async function cargarInconsistencias(id: number, paginaActual = 1) {
    const r = await apiGet<ListaPaginada<InconsistenciaMigracion>>(
      withQuery(`/api/migracion/${id}/inconsistencias`, {
        page: paginaActual,
        per_page: 20,
      }),
    );
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible cargar las inconsistencias.");
      return;
    }
    setInconsistencias(r.data.items);
    setPagina(r.data.pagination.current_page);
    setUltima(r.data.pagination.last_page);
  }

  async function validar(evento: FormEvent) {
    evento.preventDefault();
    if (!archivo) {
      setError("Debe seleccionar un archivo.");
      return;
    }
    setValidando(true);
    setError(null);
    setMensaje(null);
    const form = new FormData();
    form.append("archivo", archivo);
    form.append("anio_programa", anio);
    const r = await apiPostForm<Migracion>("/api/migracion/validar", form);
    setValidando(false);
    if (!r.success || !r.data) {
      setMigracion(null);
      setInconsistencias([]);
      setError(r.message || "No fue posible procesar el archivo. Verifique que corresponde a la matriz HSEQ requerida.");
      return;
    }
    setMigracion(r.data);
    setMensaje(r.message);
    await cargarInconsistencias(r.data.migracion_id, 1);
  }

  async function confirmar() {
    if (!migracion) return;
    setConfirmando(true);
    setError(null);
    const r = await apiPost<Migracion>(`/api/migracion/${migracion.migracion_id}/confirmar`);
    setConfirmando(false);
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible confirmar la migración.");
      return;
    }
    setMigracion(r.data);
    setMensaje(r.message);
  }

  async function cancelar() {
    if (!migracion) return;
    setCancelando(true);
    setError(null);
    const r = await apiPost<Migracion>(`/api/migracion/${migracion.migracion_id}/cancelar`);
    setCancelando(false);
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible cancelar la migración.");
      return;
    }
    setMigracion(r.data);
    setMensaje(r.message);
  }

  async function descargarReporte() {
    if (!migracion) return;
    try {
      await apiDownload(
        `/api/migracion/${migracion.migracion_id}/reporte`,
        "Reporte_inconsistencias_migracion.xlsx",
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : "No fue posible descargar el reporte.");
    }
  }

  const pendiente = migracion?.estado === "VALIDADA";
  const estructuraOk = Boolean(migracion?.resumen?.estructura_valida);

  return (
    <>
      <PageHeader
        titulo="Carga inicial desde Excel"
        descripcion="Valide la matriz HSEQ-PRG-10, revise inconsistencias y confirme la importación. No se escriben datos hasta confirmar."
      />
      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <form onSubmit={validar} className="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
        <Field etiqueta="Archivo Excel (.xlsx)">
          <input
            className={inputClass}
            type="file"
            accept=".xlsx,.xls"
            onChange={(e) => setArchivo(e.target.files?.[0] ?? null)}
          />
        </Field>
        <Field etiqueta="Año del programa">
          <input
            className={inputClass}
            type="number"
            min={2000}
            max={2100}
            value={anio}
            onChange={(e) => setAnio(e.target.value)}
          />
        </Field>
        <div className="flex items-end">
          <Button type="submit" disabled={validando}>
            {validando ? "Validando…" : "Validar archivo"}
          </Button>
        </div>
      </form>

      {migracion ? (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center gap-3">
            <Badge tono={tonoEstado(migracion.estado)}>{migracion.estado}</Badge>
            <span className="text-sm text-slate-600">{migracion.nombre_archivo}</span>
            <span className="text-sm text-slate-500">Año {migracion.anio_programa}</span>
          </div>

          <Hojas resumen={migracion.resumen} />

          <ResumenValidacion resumen={migracion.resumen} />

          {migracion.conteos && migracion.estado === "CONFIRMADA" ? (
            <ConteosFinales conteos={migracion.conteos} />
          ) : null}

          <div className="flex flex-wrap gap-2">
            <Button type="button" variante="secondary" onClick={() => void descargarReporte()}>
              Descargar reporte de inconsistencias
            </Button>
            <Button
              type="button"
              variante="secondary"
              onClick={() => {
                if (!migracion) return;
                void apiDownload(
                  `/api/migracion/${migracion.migracion_id}/archivo`,
                  migracion.nombre_archivo || "origen.xlsx",
                ).catch((e) => {
                  setError(e instanceof Error ? e.message : "No fue posible descargar el archivo original.");
                });
              }}
            >
              Descargar archivo original
            </Button>
            {pendiente ? (
              <>
                <Button type="button" variante="danger" disabled={cancelando} onClick={() => void cancelar()}>
                  {cancelando ? "Cancelando…" : "Cancelar"}
                </Button>
                <Button
                  type="button"
                  disabled={confirmando || !estructuraOk}
                  onClick={() => void confirmar()}
                >
                  {confirmando ? "Confirmando…" : "Confirmar importación"}
                </Button>
              </>
            ) : null}
          </div>

          {!estructuraOk && pendiente ? (
            <Alert tono="aviso">
              Faltan hojas obligatorias o la estructura no corresponde a la matriz HSEQ. Corrija el archivo antes de confirmar.
            </Alert>
          ) : null}

          <Table
            columnas={[
              { clave: "hoja", etiqueta: "Hoja" },
              { clave: "fila", etiqueta: "Fila" },
              { clave: "tipo", etiqueta: "Tipo" },
              { clave: "id", etiqueta: "Identificador" },
              { clave: "motivo", etiqueta: "Motivo" },
              { clave: "sev", etiqueta: "Severidad" },
            ]}
            filas={inconsistencias.map((item) => [
              item.hoja,
              item.fila || "—",
              item.tipo,
              item.identificador || "—",
              item.motivo,
              <Badge key={`${item.fila}-${item.campo}`} tono={item.severidad === "Advertencia" ? "aviso" : "alto"}>
                {item.severidad}
              </Badge>,
            ])}
            vacio="No se detectaron inconsistencias."
          />
          <Pagination
            pagina={pagina}
            ultima={ultima}
            onCambiar={(p) => {
              if (migracion) void cargarInconsistencias(migracion.migracion_id, p);
            }}
          />
        </div>
      ) : null}
    </>
  );
}

function tonoEstado(estado: string): "ok" | "aviso" | "alto" | "neutral" {
  if (estado === "CONFIRMADA") return "ok";
  if (estado === "VALIDADA") return "aviso";
  if (estado === "FALLIDA") return "alto";
  return "neutral";
}

function Hojas({ resumen }: { resumen: Migracion["resumen"] }) {
  const detectadas = resumen?.hojas_detectadas ?? [];
  const faltantes = resumen?.hojas_faltantes ?? [];
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm">
      <p className="font-medium text-slate-800">Hojas del archivo</p>
      <p className="mt-1 text-slate-600">
        Detectadas: {detectadas.length ? detectadas.join(", ") : "ninguna"}
      </p>
      {faltantes.length ? (
        <p className="mt-1 text-red-700">Faltantes: {faltantes.join(", ")}</p>
      ) : (
        <p className="mt-1 text-emerald-700">Hojas obligatorias presentes.</p>
      )}
    </div>
  );
}

function ResumenValidacion({ resumen }: { resumen: Migracion["resumen"] }) {
  const filas: [string, ConteoMigracion | undefined][] = [
    ["Trabajadores", resumen?.trabajadores],
    ["Capacitaciones", resumen?.capacitaciones],
    ["Matriz", resumen?.matriz],
    ["Cumplimientos (E)", resumen?.cumplimientos],
  ];
  return (
    <div>
      <h2 className="mb-2 text-sm font-semibold text-slate-800">Resumen de validación</h2>
      <Table
        columnas={[
          { clave: "tipo", etiqueta: "Tipo" },
          { clave: "d", etiqueta: "Detectados" },
          { clave: "v", etiqueta: "Válidos" },
          { clave: "i", etiqueta: "Inconsistencias" },
          { clave: "e", etiqueta: "Ya en sistema" },
        ]}
        filas={filas.map(([etiqueta, bloque]) => [
          etiqueta,
          bloque?.detectados ?? 0,
          bloque?.validos ?? 0,
          bloque?.inconsistencias ?? 0,
          bloque?.existentes ?? 0,
        ])}
      />
      <p className="mt-2 text-sm text-slate-500">
        {resumen?.errores ?? 0} error(es), {resumen?.advertencias ?? 0} advertencia(s).
      </p>
    </div>
  );
}

function ConteosFinales({ conteos }: { conteos: Record<string, ConteoMigracion> }) {
  const filas: [string, string][] = [
    ["Trabajadores", "trabajadores"],
    ["Capacitaciones", "capacitaciones"],
    ["Matriz", "matriz"],
    ["Cumplimientos", "cumplimientos"],
  ];
  return (
    <div>
      <h2 className="mb-2 text-sm font-semibold text-slate-800">Resultado de la importación</h2>
      <Table
        columnas={[
          { clave: "tipo", etiqueta: "Tipo" },
          { clave: "excel", etiqueta: "Excel" },
          { clave: "imp", etiqueta: "Importados" },
          { clave: "rec", etiqueta: "Rechazados" },
          { clave: "sis", etiqueta: "Sistema" },
          { clave: "dif", etiqueta: "Diferencia" },
        ]}
        filas={filas.map(([etiqueta, clave]) => {
          const b = conteos[clave] ?? {};
          return [
            etiqueta,
            b.excel ?? 0,
            b.importados ?? 0,
            b.rechazados ?? 0,
            b.sistema ?? 0,
            b.diferencia ?? 0,
          ];
        })}
      />
      <p className="mt-2 text-sm text-slate-500">
        Sistema cuenta las claves de este archivo que quedaron en la base. La diferencia suele ser rechazos o registros que ya existían.
      </p>
    </div>
  );
}
