"use client";

import { useEffect, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";
import type { CambioAuditoria, RegistroAuditoria } from "@/lib/tipos";

const MODULOS = [
  { valor: "", etiqueta: "Todos" },
  { valor: "capacitaciones", etiqueta: "Capacitaciones" },
  { valor: "asignaciones_capacitacion", etiqueta: "Asignaciones" },
  { valor: "cumplimientos_capacitacion", etiqueta: "Cumplimientos" },
  { valor: "soportes_cumplimiento", etiqueta: "Evidencias" },
  { valor: "migraciones", etiqueta: "Migración Excel" },
  { valor: "personal", etiqueta: "Personal" },
];

const ACCIONES = [
  { valor: "", etiqueta: "Todas" },
  { valor: "crear", etiqueta: "Crear" },
  { valor: "actualizar", etiqueta: "Actualizar" },
  { valor: "eliminar", etiqueta: "Eliminar" },
  { valor: "cargar", etiqueta: "Cargar" },
  { valor: "asignar_masivo", etiqueta: "Asignar masivo" },
  { valor: "generar_automaticas", etiqueta: "Motor automático" },
  { valor: "migracion_inicial", etiqueta: "Carga inicial Excel" },
  { valor: "inactivar", etiqueta: "Inactivar" },
];

function formatoFechaHora(valor: string | null): string {
  if (!valor) return "—";
  const m = valor.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
  if (!m) return valor;
  return `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}:${m[6]}`;
}

function textoValor(valor: unknown): string {
  if (valor === null || valor === undefined || valor === "") {
    return "—";
  }
  if (typeof valor === "string" || typeof valor === "number" || typeof valor === "boolean") {
    return String(valor);
  }
  return JSON.stringify(valor);
}

export default function AuditoriaPage() {
  return (
    <RequierePermiso permiso="auditoria.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const [items, setItems] = useState<RegistroAuditoria[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [entidad, setEntidad] = useState("");
  const [accion, setAccion] = useState("");
  const [usuario, setUsuario] = useState("");
  const [desde, setDesde] = useState("");
  const [hasta, setHasta] = useState("");
  const [expandida, setExpandida] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function cargar(paginaActual = 1) {
    const r = await apiGet<ListaPaginada<RegistroAuditoria>>(
      withQuery("/api/auditoria", {
        page: paginaActual,
        per_page: 20,
        entidad,
        accion,
        usuario,
        desde,
        hasta,
      }),
    );
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible cargar la auditoría.");
      return;
    }
    setItems(r.data.items);
    setPagina(r.data.pagination.current_page);
    setUltima(r.data.pagination.last_page);
    setError(null);
    setExpandida(null);
  }

  useEffect(() => {
    void cargar(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <>
      <PageHeader
        titulo="Auditoría"
        descripcion="Registro de altas, cambios y bajas del módulo HSEQ."
      />
      {error ? <Alert tono="error">{error}</Alert> : null}
      <Filters>
        <Field etiqueta="Módulo">
          <select className={inputClass} value={entidad} onChange={(e) => setEntidad(e.target.value)}>
            {MODULOS.map((op) => (
              <option key={op.valor || "todos"} value={op.valor}>
                {op.etiqueta}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Acción">
          <select className={inputClass} value={accion} onChange={(e) => setAccion(e.target.value)}>
            {ACCIONES.map((op) => (
              <option key={op.valor || "todas"} value={op.valor}>
                {op.etiqueta}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Usuario">
          <input
            className={inputClass}
            value={usuario}
            onChange={(e) => setUsuario(e.target.value)}
            placeholder="Nombre o usuario"
          />
        </Field>
        <Field etiqueta="Desde">
          <input type="date" className={inputClass} value={desde} onChange={(e) => setDesde(e.target.value)} />
        </Field>
        <Field etiqueta="Hasta">
          <input type="date" className={inputClass} value={hasta} onChange={(e) => setHasta(e.target.value)} />
        </Field>
        <div className="flex items-end">
          <Button type="button" variante="secondary" onClick={() => void cargar(1)}>
            Filtrar
          </Button>
        </div>
      </Filters>
      <div className="overflow-x-auto rounded-xl border border-slate-200">
        <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th className="px-4 py-3 font-medium">Fecha y hora</th>
              <th className="px-4 py-3 font-medium">Usuario</th>
              <th className="px-4 py-3 font-medium">Acción</th>
              <th className="px-4 py-3 font-medium">Entidad</th>
              <th className="px-4 py-3 font-medium">Id</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 bg-white">
            {items.length === 0 ? (
              <tr>
                <td className="px-4 py-8 text-center text-slate-500" colSpan={5}>
                  No hay registros para mostrar.
                </td>
              </tr>
            ) : (
              items.map((item) => {
                const abierta = expandida === item.auditoria_id;
                return (
                  <FilaAuditoria
                    key={item.auditoria_id}
                    item={item}
                    abierta={abierta}
                    onToggle={() =>
                      setExpandida(abierta ? null : item.auditoria_id)
                    }
                  />
                );
              })
            )}
          </tbody>
        </table>
      </div>
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />
    </>
  );
}

function FilaAuditoria({
  item,
  abierta,
  onToggle,
}: {
  item: RegistroAuditoria;
  abierta: boolean;
  onToggle: () => void;
}) {
  const cambios: CambioAuditoria[] = Array.isArray(item.cambios) ? item.cambios : [];

  return (
    <>
      <tr
        className="cursor-pointer hover:bg-hseq-50/40"
        onClick={onToggle}
      >
        <td className="px-4 py-3 align-top text-slate-700">{formatoFechaHora(item.created_at)}</td>
        <td className="px-4 py-3 align-top text-slate-700">{item.nombre_usuario ?? "—"}</td>
        <td className="px-4 py-3 align-top text-slate-700">{item.accion}</td>
        <td className="px-4 py-3 align-top text-slate-700">{item.entidad ?? "—"}</td>
        <td className="px-4 py-3 align-top text-slate-700">{item.entidad_id ?? "—"}</td>
      </tr>
      {abierta ? (
        <tr className="bg-slate-50">
          <td className="px-4 py-3" colSpan={5}>
            {item.origen ? (
              <p className="mb-2 text-xs text-slate-500">Origen: {item.origen}</p>
            ) : null}
            {cambios.length > 0 ? (
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th className="py-1 pr-4">Campo</th>
                    <th className="py-1 pr-4">Anterior</th>
                    <th className="py-1">Nuevo</th>
                  </tr>
                </thead>
                <tbody>
                  {cambios.map((cambio) => (
                    <tr key={cambio.campo} className="border-t border-slate-200">
                      <td className="py-1 pr-4 font-medium text-slate-700">
                        {cambio.etiqueta || cambio.campo}
                      </td>
                      <td className="py-1 pr-4 text-slate-600">{textoValor(cambio.anterior)}</td>
                      <td className="py-1 text-slate-600">{textoValor(cambio.nuevo)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <dl className="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-xs uppercase tracking-wide text-slate-500">Anterior</dt>
                  <dd className="mt-1 whitespace-pre-wrap break-all text-slate-700">
                    {textoValor(item.valor_anterior)}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs uppercase tracking-wide text-slate-500">Nuevo</dt>
                  <dd className="mt-1 whitespace-pre-wrap break-all text-slate-700">
                    {textoValor(item.valor_nuevo ?? item.detalle)}
                  </dd>
                </div>
              </dl>
            )}
          </td>
        </tr>
      ) : null}
    </>
  );
}
