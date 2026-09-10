"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { Alert } from "@/components/ui/alert";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, MasFiltros, type ChipFiltro } from "@/components/ui/filtros-activos";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { useDebouncedCallback, useFiltrosUrl } from "@/hooks/useFiltrosUrl";
import { apiGet, withQuery, type ListaPaginada } from "@/lib/api";
import type { CambioAuditoria, RegistroAuditoria } from "@/lib/tipos";

const FILTROS_DEFAULT = {
  buscar: "",
  modulo: "",
  accion: "",
  usuario: "",
  entidad_id: "",
  desde: "",
  hasta: "",
};

const MODULOS = [
  { valor: "", etiqueta: "Todos" },
  { valor: "capacitaciones", etiqueta: "Capacitaciones" },
  { valor: "matriz", etiqueta: "Matriz de aplicabilidad" },
  { valor: "plan-anual", etiqueta: "Plan anual" },
  { valor: "cronograma", etiqueta: "Tablero de Cronograma" },
  { valor: "asignaciones", etiqueta: "Asignaciones" },
  { valor: "cumplimientos", etiqueta: "Cumplimientos" },
  { valor: "catalogos", etiqueta: "Catálogos" },
  { valor: "personal", etiqueta: "Personal" },
  { valor: "migracion", etiqueta: "Carga inicial Excel" },
  { valor: "reportes", etiqueta: "Reportes" },
];

const ACCIONES = [
  { valor: "", etiqueta: "Todas" },
  { valor: "crear", etiqueta: "Crear" },
  { valor: "actualizar", etiqueta: "Editar" },
  { valor: "inactivar", etiqueta: "Inactivar" },
  { valor: "reactivar", etiqueta: "Reactivar" },
  { valor: "eliminar", etiqueta: "Eliminar" },
  { valor: "cargar", etiqueta: "Cargar soporte" },
  { valor: "descargar", etiqueta: "Descargar soporte" },
  { valor: "asignar_masivo", etiqueta: "Asignación masiva" },
  { valor: "generar_automaticas", etiqueta: "Asignación automática" },
  { valor: "sincronizar", etiqueta: "Guardado masivo de matriz" },
  { valor: "asociar_masivo", etiqueta: "Asociación masiva de matriz" },
  { valor: "aprobar", etiqueta: "Aprobar" },
  { valor: "devolver", etiqueta: "Devolver" },
  { valor: "enviar_revision", etiqueta: "Enviar a revisión" },
  { valor: "reprogramar", etiqueta: "Reprogramar" },
  { valor: "cancelar", etiqueta: "Cancelar" },
  { valor: "iniciar", etiqueta: "Iniciar ejecución" },
  { valor: "asistencia", etiqueta: "Registrar asistencia" },
  { valor: "finalizar", etiqueta: "Finalizar" },
  { valor: "convocar", etiqueta: "Convocar" },
  { valor: "retirar_convocado", etiqueta: "Retirar convocado" },
  { valor: "registrar_evaluaciones", etiqueta: "Registrar evaluación" },
  { valor: "registrar_masivo", etiqueta: "Registro masivo de cumplimientos" },
  { valor: "crear_actividad", etiqueta: "Agregar actividad" },
  { valor: "editar_actividad", etiqueta: "Editar actividad" },
  { valor: "eliminar_actividad", etiqueta: "Retirar actividad" },
  { valor: "incluir_asignaciones", etiqueta: "Incluir asignaciones" },
  { valor: "quitar_asignacion", etiqueta: "Quitar asignación del plan" },
  { valor: "mover_asignacion", etiqueta: "Mover asignación" },
  { valor: "migracion_inicial", etiqueta: "Carga inicial Excel" },
  { valor: "exportar", etiqueta: "Exportar reporte" },
  { valor: "importar", etiqueta: "Importar personal" },
];

const ACCIONES_UNICAS = ACCIONES.filter(
  (op, idx, arr) => arr.findIndex((o) => o.valor === op.valor) === idx,
);

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

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
  if (typeof valor === "boolean") {
    return valor ? "Sí" : "No";
  }
  if (typeof valor === "string" || typeof valor === "number") {
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
  const { valores, setFiltro, limpiar } = useFiltrosUrl(FILTROS_DEFAULT, {
    keysDebounce: ["buscar", "usuario", "entidad_id"],
  });
  const [items, setItems] = useState<RegistroAuditoria[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargando, setCargando] = useState(true);
  const [expandida, setExpandida] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [masFiltros, setMasFiltros] = useState(() =>
    Boolean(valores.entidad_id || valores.desde || valores.hasta),
  );

  async function cargar(paginaActual = 1) {
    setCargando(true);
    const r = await apiGet<ListaPaginada<RegistroAuditoria>>(
      withQuery("/api/auditoria", {
        page: paginaActual,
        per_page: 20,
        q: valores.buscar.trim() || undefined,
        modulo: valores.modulo || undefined,
        accion: valores.accion || undefined,
        usuario: valores.usuario.trim() || undefined,
        desde: valores.desde || undefined,
        hasta: valores.hasta || undefined,
        entidad_id: valores.entidad_id || undefined,
      }),
    );
    setCargando(false);
    if (r.cancelada) {
      return;
    }
    if (!r.success || !r.data) {
      setError(r.message || "No fue posible consultar la auditoría.");
      return;
    }
    setItems(r.data.items);
    setPagina(r.data.pagination.current_page);
    setUltima(r.data.pagination.last_page);
    setError(null);
    setExpandida(null);
  }

  useDebouncedCallback(() => {
    void cargar(1);
  }, [
    valores.buscar,
    valores.modulo,
    valores.accion,
    valores.usuario,
    valores.desde,
    valores.hasta,
    valores.entidad_id,
  ]);

  const chipsActivos = useMemo(() => {
    const chips: ChipFiltro[] = [];
    if (valores.buscar.trim()) {
      chips.push({ clave: "buscar", etiqueta: "Buscar", valor: valores.buscar.trim() });
    }
    if (valores.modulo) {
      chips.push({
        clave: "modulo",
        etiqueta: "Módulo",
        valor: MODULOS.find((m) => m.valor === valores.modulo)?.etiqueta ?? valores.modulo,
      });
    }
    if (valores.accion) {
      chips.push({
        clave: "accion",
        etiqueta: "Acción",
        valor: ACCIONES_UNICAS.find((a) => a.valor === valores.accion)?.etiqueta ?? valores.accion,
      });
    }
    if (valores.usuario.trim()) {
      chips.push({ clave: "usuario", etiqueta: "Usuario", valor: valores.usuario.trim() });
    }
    if (valores.entidad_id) {
      chips.push({ clave: "entidad_id", etiqueta: "Identificador", valor: valores.entidad_id });
    }
    if (valores.desde) {
      chips.push({ clave: "desde", etiqueta: "Fecha desde", valor: formatoFecha(valores.desde) });
    }
    if (valores.hasta) {
      chips.push({ clave: "hasta", etiqueta: "Fecha hasta", valor: formatoFecha(valores.hasta) });
    }
    return chips;
  }, [valores]);

  const extrasActivos = [valores.entidad_id, valores.desde, valores.hasta].filter(Boolean).length;
  const vacio =
    chipsActivos.length > 0
      ? "No se encontraron eventos para los filtros seleccionados."
      : "No hay eventos de auditoría para mostrar.";

  return (
    <>
      <PageHeader
        titulo="Auditoría"
        descripcion="Consulta de trazabilidad: quién hizo qué, cuándo y qué cambió. Los eventos no se pueden editar ni eliminar."
      />
      {error ? <Alert tono="error">{error}</Alert> : null}

      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={valores.buscar}
            onChange={(e) => setFiltro("buscar", e.target.value)}
            placeholder="Usuario, acción, módulo o id"
          />
        </Field>
        <Field etiqueta="Módulo">
          <select
            className={inputClass}
            value={valores.modulo}
            onChange={(e) => setFiltro("modulo", e.target.value)}
          >
            {MODULOS.map((op) => (
              <option key={op.valor || "todos"} value={op.valor}>
                {op.etiqueta}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Acción">
          <select
            className={inputClass}
            value={valores.accion}
            onChange={(e) => setFiltro("accion", e.target.value)}
          >
            {ACCIONES_UNICAS.map((op) => (
              <option key={op.valor || "todas"} value={op.valor}>
                {op.etiqueta}
              </option>
            ))}
          </select>
        </Field>
        <Field etiqueta="Usuario">
          <input
            className={inputClass}
            value={valores.usuario}
            onChange={(e) => setFiltro("usuario", e.target.value)}
            placeholder="Nombre o usuario"
          />
        </Field>
      </Filters>

      <MasFiltros
        abierto={masFiltros}
        onToggle={() => setMasFiltros((abierto) => !abierto)}
        extrasActivos={extrasActivos}
      >
            <Field etiqueta="Identificador">
              <input
                className={inputClass}
                value={valores.entidad_id}
                onChange={(e) => setFiltro("entidad_id", e.target.value.replace(/[^\d]/g, ""))}
                placeholder="Id del registro"
                inputMode="numeric"
              />
            </Field>
            <Field etiqueta="Fecha desde">
              <input
                type="date"
                className={inputClass}
                value={valores.desde}
                onChange={(e) => setFiltro("desde", e.target.value)}
              />
            </Field>
            <Field etiqueta="Fecha hasta">
              <input
                type="date"
                className={inputClass}
                value={valores.hasta}
                onChange={(e) => setFiltro("hasta", e.target.value)}
              />
            </Field>
      </MasFiltros>

      <FiltrosActivos
        chips={chipsActivos}
        onQuitar={(clave) => setFiltro(clave, "")}
        onLimpiar={limpiar}
      />

      {cargando ? (
        <ListaCargando mensaje="Cargando auditoría…" />
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Fecha y hora</th>
                <th className="px-4 py-3 font-medium">Usuario</th>
                <th className="px-4 py-3 font-medium">Módulo</th>
                <th className="px-4 py-3 font-medium">Acción</th>
                <th className="px-4 py-3 font-medium">Registro</th>
                <th className="px-4 py-3 font-medium">Resumen</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {items.length === 0 ? (
                <tr>
                  <td className="px-4 py-8 text-center text-slate-500" colSpan={6}>
                    {vacio}
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
      )}
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
  const modulo = item.modulo_etiqueta ?? item.entidad ?? "—";
  const accion = item.accion_etiqueta ?? item.accion;
  const ruta = item.ruta_relacionada;

  return (
    <>
      <tr
        className="cursor-pointer hover:bg-hseq-50/40"
        onClick={onToggle}
      >
        <td className="px-4 py-3 align-top text-slate-700">{formatoFechaHora(item.created_at)}</td>
        <td className="px-4 py-3 align-top text-slate-700">{item.nombre_usuario ?? "—"}</td>
        <td className="px-4 py-3 align-top text-slate-700">{modulo}</td>
        <td className="px-4 py-3 align-top text-slate-700">{accion}</td>
        <td className="px-4 py-3 align-top text-slate-700">{item.entidad_id ?? "—"}</td>
        <td className="px-4 py-3 align-top text-slate-700">{item.resumen ?? "—"}</td>
      </tr>
      {abierta ? (
        <tr className="bg-slate-50">
          <td className="px-4 py-3" colSpan={6}>
            <div className="mb-3 flex flex-wrap items-center gap-3 text-sm">
              {item.origen ? (
                <p className="text-xs text-slate-500">Origen: {item.origen}</p>
              ) : null}
              {ruta ? (
                <Link
                  href={ruta}
                  prefetch={false}
                  className="text-sm font-medium text-hseq-700 hover:underline"
                  onClick={(e) => e.stopPropagation()}
                >
                  Ver módulo relacionado
                </Link>
              ) : null}
            </div>
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
                  {cambios.map((cambio, idx) => (
                    <tr key={`${cambio.campo}-${idx}`} className="border-t border-slate-200">
                      <td className="py-1 pr-4 font-medium text-slate-700">
                        {cambio.etiqueta || cambio.campo}
                        {"persona_nombre" in cambio && cambio.persona_nombre
                          ? ` (${String(cambio.persona_nombre)})`
                          : null}
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
