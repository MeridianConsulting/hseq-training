"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { useDebouncedCallback, useFiltrosUrl } from "@/hooks/useFiltrosUrl";
import { Download, History, Pencil, Plus, Upload, UserMinus } from "lucide-react";
import {
  apiDownload,
  apiGet,
  apiPost,
  apiPostForm,
  apiPut,
  withQuery,
  type ApiErrorMap,
  type ListaPaginada,
} from "@/lib/api";
import type {
  CargoCorporativo,
  PersonaCorporativa,
  ResultadoCargaPersonal,
  TipoDocumentoCorporativo,
} from "@/lib/tipos";
import { FormularioTrabajador, type DatosTrabajador } from "./formulario";

export default function PersonalPage() {
  return (
    <RequierePermiso permiso="personal.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const { valores, setFiltro, limpiar } = useFiltrosUrl({
    buscar: "",
    estado: "Activo",
    cargo_id: "",
  });
  const [items, setItems] = useState<PersonaCorporativa[]>([]);
  const [cargos, setCargos] = useState<CargoCorporativo[]>([]);
  const [tiposDocumento, setTiposDocumento] = useState<TipoDocumentoCorporativo[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [abierto, setAbierto] = useState(false);
  const [cargaAbierta, setCargaAbierta] = useState(false);
  const [editando, setEditando] = useState<PersonaCorporativa | null>(null);
  const [erroresApi, setErroresApi] = useState<ApiErrorMap | null>(null);
  const [archivo, setArchivo] = useState<File | null>(null);
  const [resultadoCarga, setResultadoCarga] = useState<ResultadoCargaPersonal | null>(null);
  const [cargandoImportacion, setCargandoImportacion] = useState(false);
  const [personaHistorial, setPersonaHistorial] = useState<PersonaCorporativa | null>(null);
  const [inactivarDe, setInactivarDe] = useState<PersonaCorporativa | null>(null);
  const [inactivando, setInactivando] = useState(false);

  async function cargar(paginaActual = 1) {
    setCargando(true);
    const respuesta = await apiGet<ListaPaginada<PersonaCorporativa>>(
      withQuery("/api/personal", {
        page: paginaActual,
        per_page: 15,
        buscar: valores.buscar,
        estado: valores.estado,
        cargo_id: valores.cargo_id || undefined,
      }),
    );
    setCargando(false);

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No fue posible consultar el personal corporativo.");
      return;
    }

    setItems(respuesta.data.items);
    setPagina(respuesta.data.pagination.current_page);
    setUltima(respuesta.data.pagination.last_page);
    setError(null);
  }

  useEffect(() => {
    void (async () => {
      const [rCargos, rTipos] = await Promise.all([
        apiGet<CargoCorporativo[]>("/api/personal/cargos"),
        apiGet<TipoDocumentoCorporativo[]>("/api/personal/tipos-documento"),
      ]);
      if (rCargos.success && rCargos.data) {
        setCargos(rCargos.data);
      }
      if (rTipos.success && rTipos.data) {
        setTiposDocumento(rTipos.data);
      }
    })();
  }, []);

  useDebouncedCallback(() => {
    void cargar(1);
  }, [valores.buscar, valores.estado, valores.cargo_id]);

  const chips: ChipFiltro[] = [];
  if (valores.buscar) {
    chips.push({ clave: "buscar", etiqueta: "Buscar", valor: valores.buscar });
  }
  if (valores.estado !== "Activo") {
    chips.push({
      clave: "estado",
      etiqueta: "Estado",
      valor: valores.estado === "" ? "Todos" : valores.estado,
    });
  }
  if (valores.cargo_id) {
    const cargo = cargos.find((c) => String(c.cargo_id) === valores.cargo_id);
    chips.push({
      clave: "cargo_id",
      etiqueta: "Cargo",
      valor: cargo?.nombre_cargo ?? valores.cargo_id,
    });
  }

  function abrirNueva() {
    setEditando(null);
    setErroresApi(null);
    setError(null);
    setAbierto(true);
  }

  function abrirEdicion(item: PersonaCorporativa) {
    setEditando(item);
    setErroresApi(null);
    setError(null);
    setAbierto(true);
  }

  function abrirCarga() {
    setArchivo(null);
    setResultadoCarga(null);
    setError(null);
    setCargaAbierta(true);
  }

  async function guardar(evento: FormEvent, datos: DatosTrabajador) {
    evento.preventDefault();
    setErroresApi(null);

    const respuesta = editando
      ? await apiPut<PersonaCorporativa>(`/api/personal/${editando.persona_id}`, {
          correo: datos.correo.trim() || null,
          cargo_id: Number(datos.cargo_id),
          proyecto: datos.proyecto.trim() || null,
        })
      : await apiPost<PersonaCorporativa>("/api/personal", {
          numero_documento: datos.numero_documento.trim(),
          nombre_completo: datos.nombre_completo.trim(),
          correo: datos.correo.trim() || null,
          cargo_id: Number(datos.cargo_id),
          proyecto: datos.proyecto.trim() || null,
          fecha_ingreso: datos.fecha_ingreso,
          tipo_documento_id: Number(datos.tipo_documento_id || 1),
        });

    if (!respuesta.success) {
      if (respuesta.errors) {
        setErroresApi(respuesta.errors);
        setError("No fue posible guardar el trabajador. Verifique la información ingresada.");
      } else {
        setError(respuesta.message || "No se pudo guardar.");
      }
      return;
    }

    setMensaje(respuesta.message);
    setPersonaHistorial(respuesta.data ?? null);
    setError(null);
    setErroresApi(null);
    setAbierto(false);
    setEditando(null);
    await cargar(pagina);
  }

  async function descargarPlantilla() {
    try {
      await apiDownload("/api/personal/plantilla", "plantilla_trabajadores.xlsx");
    } catch (e) {
      setError(e instanceof Error ? e.message : "No fue posible descargar la plantilla.");
    }
  }

  async function enviarCarga(evento: FormEvent) {
    evento.preventDefault();
    if (!archivo) {
      setError("Debe seleccionar un archivo Excel o CSV.");
      return;
    }

    setCargandoImportacion(true);
    setError(null);
    const form = new FormData();
    form.append("archivo", archivo);

    const respuesta = await apiPostForm<ResultadoCargaPersonal>("/api/personal/importar", form);
    setCargandoImportacion(false);

    if (!respuesta.success || !respuesta.data) {
      setResultadoCarga(null);
      setError(respuesta.message || "No fue posible procesar el archivo.");
      return;
    }

    setResultadoCarga(respuesta.data);
    setPersonaHistorial(null);
    setMensaje(respuesta.message);
    await cargar(1);
  }

  async function confirmarInactivar() {
    if (!inactivarDe) {
      return;
    }
    setInactivando(true);
    setError(null);
    const respuesta = await apiPost<PersonaCorporativa>(`/api/personal/${inactivarDe.persona_id}/inactivar`);
    setInactivando(false);
    if (!respuesta.success) {
      setError(respuesta.message || "No fue posible inactivar el trabajador.");
      return;
    }
    setMensaje(respuesta.message || "Trabajador inactivado correctamente.");
    setInactivarDe(null);
    await cargar(pagina);
  }

  function descargarErrores() {
    if (!resultadoCarga || resultadoCarga.rechazados.length === 0) {
      return;
    }

    const encabezado = ["Fila", "Documento", "Nombre", "Estado", "Motivo"];
    const lineas = [
      encabezado,
      ...resultadoCarga.rechazados.map((fila) => [
        String(fila.fila),
        fila.documento,
        fila.nombre,
        fila.estado,
        fila.motivo,
      ]),
    ];
    const csv = lineas.map((fila) => fila.map(escaparCsv).join(";")).join("\r\n");
    const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement("a");
    const fecha = new Date().toISOString().slice(0, 10);
    enlace.href = url;
    enlace.download = `errores_carga_personal_${fecha}.csv`;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    URL.revokeObjectURL(url);
  }

  return (
    <>
      <PageHeader
        titulo="Personal corporativo"
        descripcion="Registro individual y carga masiva sobre el maestro meridian_personal. Las asignaciones usan estos mismos trabajadores."
        acciones={
          <>
            {puede("personal.importar") ? (
              <Button type="button" variante="secondary" onClick={abrirCarga}>
                <Upload className="h-4 w-4" aria-hidden />
                Carga masiva
              </Button>
            ) : null}
            {puede("personal.crear") ? (
              <Button type="button" onClick={abrirNueva}>
                <Plus className="h-4 w-4" aria-hidden />
                Registrar trabajador
              </Button>
            ) : null}
          </>
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? (
        <Alert tono={personaHistorial?.sincronizacion?.error ? "aviso" : "ok"}>
          {mensaje}
          {personaHistorial && puede("asignaciones.ver") ? (
            <>
              {" "}
              <Link href={rutaHistorial(personaHistorial)} className="font-medium underline underline-offset-2">
                Ver historial
              </Link>
            </>
          ) : null}
        </Alert>
      ) : null}

      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={valores.buscar}
            onChange={(e) => setFiltro("buscar", e.target.value)}
            placeholder="Documento o nombre"
          />
        </Field>
        <Field etiqueta="Estado laboral">
          <select
            className={inputClass}
            value={valores.estado}
            onChange={(e) => setFiltro("estado", e.target.value)}
          >
            <option value="">Todos</option>
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
        </Field>
        <Field etiqueta="Cargo">
          <select
            className={inputClass}
            value={valores.cargo_id}
            onChange={(e) => setFiltro("cargo_id", e.target.value)}
          >
            <option value="">Todos</option>
            {cargos.map((c) => (
              <option key={c.cargo_id} value={c.cargo_id}>
                {c.nombre_cargo}
              </option>
            ))}
          </select>
        </Field>
      </Filters>

      <FiltrosActivos
        chips={chips}
        onQuitar={(clave) => setFiltro(clave, clave === "estado" ? "Activo" : "")}
        onLimpiar={limpiar}
      />

      {cargando ? (
        <ListaCargando />
      ) : (
        <Table
          columnas={[
            { clave: "doc", etiqueta: "Documento" },
            { clave: "nombre", etiqueta: "Nombre" },
            { clave: "correo", etiqueta: "Correo" },
            { clave: "cargo", etiqueta: "Cargo" },
            { clave: "proyecto", etiqueta: "Proyecto" },
            { clave: "ingreso", etiqueta: "Fecha de ingreso" },
            { clave: "estado", etiqueta: "Estado" },
            { clave: "acciones", etiqueta: "" },
          ]}
          filas={items.map((item) => [
            item.numero_documento,
            item.nombre_completo,
            item.correo_corporativo ?? "—",
            item.cargo ?? "—",
            item.proyecto ?? "—",
            item.contrato_fecha_inicio ?? "—",
            <Badge key="e" tono={item.estado === "Activo" ? "ok" : "aviso"}>
              {item.estado}
            </Badge>,
            <div key="a" className="flex justify-end gap-1">
              {puede("asignaciones.ver") ? (
                <Link
                  href={rutaHistorial(item)}
                  className="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-hseq-700 hover:bg-hseq-50"
                >
                  <History className="h-4 w-4" aria-hidden />
                  Historial
                </Link>
              ) : null}
              {puede("personal.editar") && item.estado === "Activo" ? (
                <Button
                  type="button"
                  variante="ghost"
                  onClick={() => {
                    setError(null);
                    setInactivarDe(item);
                  }}
                >
                  <UserMinus className="h-4 w-4" aria-hidden />
                  Inactivar
                </Button>
              ) : null}
              {puede("personal.editar") ? (
                <Button type="button" variante="ghost" onClick={() => abrirEdicion(item)}>
                  <Pencil className="h-4 w-4" aria-hidden />
                  Editar
                </Button>
              ) : null}
            </div>,
          ])}
        />
      )}
      <Pagination pagina={pagina} ultima={ultima} onCambiar={(p) => void cargar(p)} />

      <Modal
        abierto={abierto}
        titulo={editando ? "Editar trabajador" : "Registrar trabajador"}
        onCerrar={() => setAbierto(false)}
      >
        <FormularioTrabajador
          key={editando?.persona_id ?? "nuevo"}
          inicial={editando}
          cargos={cargos}
          tiposDocumento={tiposDocumento}
          erroresApi={erroresApi}
          onCancelar={() => setAbierto(false)}
          onGuardar={guardar}
        />
      </Modal>

      <Modal abierto={cargaAbierta} titulo="Carga masiva de trabajadores" onCerrar={() => setCargaAbierta(false)}>
        <form className="space-y-4" onSubmit={(e) => void enviarCarga(e)}>
          <p className="text-sm text-slate-600">
            Importe un Excel o CSV. Las filas válidas se guardan aunque otras tengan errores.
          </p>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variante="secondary" onClick={() => void descargarPlantilla()}>
              <Download className="h-4 w-4" aria-hidden />
              Descargar plantilla
            </Button>
          </div>
          <Field etiqueta="Archivo (.xlsx, .xls o .csv)">
            <input
              className={inputClass}
              type="file"
              accept=".xlsx,.xls,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
              onChange={(e) => setArchivo(e.target.files?.[0] ?? null)}
            />
          </Field>
          <div className="flex justify-end gap-2">
            <Button type="button" variante="secondary" onClick={() => setCargaAbierta(false)}>
              Cerrar
            </Button>
            <Button type="submit" disabled={cargandoImportacion}>
              <Upload className="h-4 w-4" aria-hidden />
              {cargandoImportacion ? "Procesando…" : "Importar"}
            </Button>
          </div>
        </form>

        {resultadoCarga ? (
          <div className="mt-6 space-y-3">
            <Alert tono={resultadoCarga.total_rechazados === 0 ? "ok" : resultadoCarga.total_importados === 0 ? "error" : "aviso"}>
              Carga finalizada. Procesados: {resultadoCarga.total_procesados}. Importados:{" "}
              {resultadoCarga.total_importados}. Rechazados: {resultadoCarga.total_rechazados}.
            </Alert>
            {resultadoCarga.rechazados.length > 0 ? (
              <>
                <div className="flex justify-end">
                  <Button type="button" variante="secondary" onClick={descargarErrores}>
                    <Download className="h-4 w-4" aria-hidden />
                    Descargar reporte de errores
                  </Button>
                </div>
                <Table
                  columnas={[
                    { clave: "fila", etiqueta: "Fila" },
                    { clave: "documento", etiqueta: "Documento" },
                    { clave: "nombre", etiqueta: "Nombre" },
                    { clave: "estado", etiqueta: "Estado" },
                    { clave: "motivo", etiqueta: "Motivo" },
                  ]}
                  filas={resultadoCarga.rechazados.map((fila) => [
                    fila.fila,
                    fila.documento || "—",
                    fila.nombre || "—",
                    fila.estado,
                    fila.motivo,
                  ])}
                />
              </>
            ) : null}
          </div>
        ) : null}
      </Modal>

      <Modal
        abierto={inactivarDe !== null}
        titulo="Inactivar trabajador"
        onCerrar={() => {
          if (!inactivando) {
            setInactivarDe(null);
          }
        }}
      >
        {inactivarDe ? (
          <div className="space-y-4">
            <p className="text-sm text-slate-700">¿Desea inactivar este trabajador?</p>
            <p className="text-sm text-slate-600">
              {inactivarDe.nombre_completo} · Documento: {inactivarDe.numero_documento}
            </p>
            <p className="text-sm text-slate-600">
              El trabajador no será eliminado. Su historial de capacitaciones, asistencias y
              evaluaciones se conservará para consultas y auditorías.
            </p>
            <p className="text-sm text-slate-600">
              El trabajador dejará de participar en los indicadores de cumplimiento vigente.
            </p>
            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variante="secondary"
                disabled={inactivando}
                onClick={() => setInactivarDe(null)}
              >
                Cancelar
              </Button>
              <Button
                type="button"
                variante="danger"
                disabled={inactivando}
                onClick={() => void confirmarInactivar()}
              >
                {inactivando ? "Inactivando…" : (
                  <>
                    <UserMinus className="h-4 w-4" aria-hidden />
                    Inactivar
                  </>
                )}
              </Button>
            </div>
          </div>
        ) : null}
      </Modal>
    </>
  );
}

function rutaHistorial(persona: {
  persona_id: number;
  nombre_completo: string;
  numero_documento: string;
}): string {
  return withQuery("/asignaciones", {
    persona_id: persona.persona_id,
    nombre: persona.nombre_completo,
    documento: persona.numero_documento,
  });
}

function escaparCsv(valor: string): string {
  if (/[;"\n\r]/.test(valor)) {
    return `"${valor.replace(/"/g, '""')}"`;
  }
  return valor;
}
