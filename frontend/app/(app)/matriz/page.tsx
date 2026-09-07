"use client";

import { useEffect, useMemo, useState } from "react";
import { RequierePermiso } from "@/components/requiere-permiso";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, type ChipFiltro } from "@/components/ui/filtros-activos";
import { PageHeader } from "@/components/ui/page-header";
import { apiGet, apiPost, withQuery } from "@/lib/api";
import type { OpcionesMatriz, ResultadoSincronizarMatriz, VistaMatriz } from "@/lib/tipos";
import { Save } from "lucide-react";

function procesoPermiteProyecto(procesoId: string, procesos: OpcionesMatriz["procesos"]): boolean {
  const seleccionado = procesos.find((p) => String(p.proceso_id) === procesoId);
  if (!seleccionado) return false;
  const n = seleccionado.nombre
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
  return n.includes("gestion de proyectos");
}

function claveCelda(cargoId: number, capId: number): string {
  return `${cargoId}:${capId}`;
}

function marcasDesdeVista(vista: VistaMatriz): Record<string, boolean> {
  const marcas: Record<string, boolean> = {};
  for (const celda of vista.celdas) {
    if (celda.activa) {
      marcas[claveCelda(celda.cargo_id_ext, celda.capacitacion_id)] = true;
    }
  }
  return marcas;
}

export default function MatrizPage() {
  return (
    <RequierePermiso permiso="matriz.ver">
      <Contenido />
    </RequierePermiso>
  );
}

function Contenido() {
  const { puede } = useAuth();
  const puedeGuardar = puede("matriz.crear") || puede("matriz.editar");
  const [opciones, setOpciones] = useState<OpcionesMatriz>({
    procesos: [],
    proyectos: [],
    cargos: [],
    capacitaciones: [],
  });
  const [procesoId, setProcesoId] = useState("");
  const [proyecto, setProyecto] = useState("");
  const [buscarCargo, setBuscarCargo] = useState("");
  const [buscarCap, setBuscarCap] = useState("");
  const [verTodos, setVerTodos] = useState(false);
  const [vista, setVista] = useState<VistaMatriz | null>(null);
  const [marcas, setMarcas] = useState<Record<string, boolean>>({});
  const [inicial, setInicial] = useState<Record<string, boolean>>({});
  const [error, setError] = useState<string | null>(null);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [cargando, setCargando] = useState(false);
  const [guardando, setGuardando] = useState(false);

  const muestraProyecto = procesoPermiteProyecto(procesoId, opciones.procesos);
  const contextoListo = procesoId !== "" && (!muestraProyecto || proyecto !== "");

  useEffect(() => {
    void (async () => {
      const respuesta = await apiGet<OpcionesMatriz>("/api/matriz/opciones");
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar los filtros de la matriz.");
        return;
      }
      setOpciones(respuesta.data);
      setError(null);
    })();
  }, []);

  useEffect(() => {
    if (!contextoListo) {
      setVista(null);
      setMarcas({});
      setInicial({});
      setCargando(false);
      return;
    }

    const abortado = { actual: false };
    void (async () => {
      setCargando(true);
      const respuesta = await apiGet<VistaMatriz>(
        withQuery("/api/matriz/vista", {
          proceso_id: procesoId,
          proyecto: muestraProyecto ? proyecto : undefined,
        }),
      );
      if (abortado.actual) {
        return;
      }
      setCargando(false);
      if (!respuesta.success || !respuesta.data) {
        setError(respuesta.message || "No fue posible cargar la matriz.");
        setVista(null);
        return;
      }
      const siguientes = marcasDesdeVista(respuesta.data);
      setVista(respuesta.data);
      setMarcas(siguientes);
      setInicial(siguientes);
      setError(null);
    })();

    return () => {
      abortado.actual = true;
    };
  }, [contextoListo, procesoId, proyecto, muestraProyecto]);

  const catalogoCargos = vista?.cargos_catalogo ?? opciones.cargos ?? [];
  const capsFuente = vista?.capacitaciones ?? opciones.capacitaciones ?? [];
  const cargosContexto = vista?.cargos ?? [];
  const mostrarCatalogoSinContexto = verTodos && !contextoListo;

  const cargosBase = useMemo(() => {
    return verTodos ? catalogoCargos : cargosContexto;
  }, [verTodos, catalogoCargos, cargosContexto]);

  const cargosVisibles = useMemo(() => {
    const q = buscarCargo.trim().toLowerCase();
    if (q === "") return cargosBase;
    return cargosBase.filter((c) => c.nombre_cargo.toLowerCase().includes(q));
  }, [cargosBase, buscarCargo]);

  const capsVisibles = useMemo(() => {
    const q = buscarCap.trim().toLowerCase();
    if (q === "") return capsFuente;
    return capsFuente.filter(
      (c) => c.codigo.toLowerCase().includes(q) || c.nombre.toLowerCase().includes(q),
    );
  }, [capsFuente, buscarCap]);

  const sucio = useMemo(() => {
    const claves = new Set([...Object.keys(marcas), ...Object.keys(inicial)]);
    for (const clave of claves) {
      if (Boolean(marcas[clave]) !== Boolean(inicial[clave])) {
        return true;
      }
    }
    return false;
  }, [marcas, inicial]);

  const chips: ChipFiltro[] = [];
  if (procesoId) {
    const proceso = opciones.procesos.find((p) => String(p.proceso_id) === procesoId);
    chips.push({ clave: "proceso_id", etiqueta: "Proceso", valor: proceso?.nombre ?? procesoId });
  }
  if (muestraProyecto && proyecto) {
    chips.push({ clave: "proyecto", etiqueta: "Proyecto", valor: proyecto });
  }
  if (buscarCargo.trim()) {
    chips.push({ clave: "buscar_cargo", etiqueta: "Cargo", valor: buscarCargo.trim() });
  }
  if (buscarCap.trim()) {
    chips.push({ clave: "buscar_cap", etiqueta: "Capacitación", valor: buscarCap.trim() });
  }
  if (verTodos) {
    chips.push({ clave: "ver_todos", etiqueta: "Cargos", valor: "Todos los del catálogo" });
  }

  function quitarChip(clave: string) {
    if (clave === "proceso_id") {
      setProcesoId("");
      setProyecto("");
    }
    if (clave === "proyecto") setProyecto("");
    if (clave === "buscar_cargo") setBuscarCargo("");
    if (clave === "buscar_cap") setBuscarCap("");
    if (clave === "ver_todos") setVerTodos(false);
  }

  function aplicarVista(siguiente: VistaMatriz) {
    const siguientes = marcasDesdeVista(siguiente);
    setVista(siguiente);
    setMarcas(siguientes);
    setInicial(siguientes);
  }

  async function guardar() {
    if (!contextoListo || !puedeGuardar) {
      return;
    }

    const aplica = Object.entries(marcas)
      .filter(([, marcada]) => marcada)
      .map(([clave]) => {
        const [cargo, cap] = clave.split(":");
        return { cargo_id_ext: Number(cargo), capacitacion_id: Number(cap) };
      });

    setGuardando(true);
    const respuesta = await apiPost<ResultadoSincronizarMatriz>("/api/matriz/sincronizar", {
      proceso_id: Number(procesoId),
      proyecto: muestraProyecto ? proyecto : null,
      aplica,
    });
    setGuardando(false);

    if (!respuesta.success || !respuesta.data) {
      setError(respuesta.message || "No se pudo guardar la matriz.");
      return;
    }

    aplicarVista(respuesta.data.vista);
    setMensaje(respuesta.message || "Matriz de aplicabilidad guardada");
    setError(null);
  }

  return (
    <>
      <PageHeader
        titulo="Matriz de aplicabilidad"
        descripcion="Marque las capacitaciones que aplican a cada cargo según el proceso y, si corresponde, el proyecto. Las asignaciones se generan en el módulo de asignaciones."
        acciones={
          puedeGuardar ? (
            <Button type="button" disabled={!contextoListo || !sucio || guardando} onClick={() => void guardar()}>
              <Save className="h-4 w-4" aria-hidden />
              {guardando ? "Guardando…" : "Guardar"}
            </Button>
          ) : null
        }
      />

      {error ? <Alert tono="error">{error}</Alert> : null}
      {mensaje ? <Alert tono="ok">{mensaje}</Alert> : null}

      <Filters>
        <Field etiqueta="Proceso">
          <select
            className={inputClass}
            value={procesoId}
            onChange={(e) => {
              const valor = e.target.value;
              setProcesoId(valor);
              if (!procesoPermiteProyecto(valor, opciones.procesos)) {
                setProyecto("");
              }
            }}
          >
            <option value="">Seleccione</option>
            {opciones.procesos.map((item) => (
              <option key={item.proceso_id} value={item.proceso_id}>
                {item.nombre}
              </option>
            ))}
          </select>
        </Field>

        {muestraProyecto ? (
          <Field etiqueta="Proyecto">
            <select className={inputClass} value={proyecto} onChange={(e) => setProyecto(e.target.value)}>
              <option value="">Seleccione</option>
              {opciones.proyectos.map((nombre) => (
                <option key={nombre} value={nombre}>
                  {nombre}
                </option>
              ))}
            </select>
          </Field>
        ) : null}

        <Field etiqueta="Cargos del contexto">
          <label className="flex items-center gap-2 pt-2 text-sm text-slate-700">
            <input
              type="checkbox"
              className="h-4 w-4 accent-hseq-800"
              checked={verTodos}
              onChange={(e) => setVerTodos(e.target.checked)}
            />
            Ver todos los cargos
          </label>
        </Field>
        <Field etiqueta="Buscar cargo">
          <input
            className={inputClass}
            value={buscarCargo}
            onChange={(e) => setBuscarCargo(e.target.value)}
            placeholder="Nombre del cargo"
          />
        </Field>
        <Field etiqueta="Buscar capacitación">
          <input
            className={inputClass}
            value={buscarCap}
            onChange={(e) => setBuscarCap(e.target.value)}
            placeholder="Código o nombre"
          />
        </Field>
      </Filters>

      <FiltrosActivos
        chips={chips}
        onQuitar={quitarChip}
        onLimpiar={() => {
          setProcesoId("");
          setProyecto("");
          setBuscarCargo("");
          setBuscarCap("");
          setVerTodos(false);
        }}
      />

      {!contextoListo && !verTodos ? (
        <p className="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
          {procesoId === ""
            ? "Seleccione un proceso para editar la matriz, o active «Ver todos los cargos» para consultar el catálogo."
            : "Seleccione un proyecto para editar la matriz, o active «Ver todos los cargos» para consultar el catálogo."}
        </p>
      ) : cargando && contextoListo ? (
        <ListaCargando />
      ) : (vista && contextoListo) || mostrarCatalogoSinContexto ? (
        <>
        {catalogoCargos.length !== cargosContexto.length || mostrarCatalogoSinContexto ? (
          <p className="mb-2 text-xs text-slate-500">
            {mostrarCatalogoSinContexto
              ? `Mostrando los ${catalogoCargos.length} cargos del catálogo. Seleccione un proceso para marcar qué aplica.`
              : verTodos
                ? `Mostrando los ${catalogoCargos.length} cargos del catálogo. Desactive «Ver todos» para volver a los ${cargosContexto.length} de este contexto.`
                : `Mostrando ${cargosContexto.length} cargos de este contexto (de ${catalogoCargos.length} del catálogo).`}
          </p>
        ) : null}
        <div className="overflow-auto rounded-xl border border-slate-200 bg-white max-h-[70vh]">
          <table className="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th className="sticky left-0 top-0 z-30 min-w-[12rem] border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                  Cargo
                </th>
                {capsVisibles.map((cap) => (
                  <th
                    key={cap.capacitacion_id}
                    className="sticky top-0 z-20 min-w-[7.5rem] border-b border-slate-200 bg-slate-50 px-2 py-2 text-center align-bottom"
                  >
                    <div className="flex flex-col items-center gap-1">
                      <span className="font-semibold text-slate-800">{cap.codigo}</span>
                      <span className="max-w-[8rem] text-[11px] font-normal leading-tight text-slate-500">
                        {cap.nombre}
                      </span>
                      {cap.es_tarea_critica ? <Badge tono="alto">Crítica</Badge> : null}
                    </div>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {cargosVisibles.length === 0 || capsVisibles.length === 0 ? (
                <tr>
                  <td
                    className="px-4 py-8 text-center text-slate-500"
                    colSpan={Math.max(1, capsVisibles.length + 1)}
                  >
                    {cargosContexto.length === 0 && !verTodos && buscarCargo.trim() === ""
                      ? "No hay cargos definidos para este proceso en la matriz. Active «Ver todos los cargos» para marcar uno del catálogo."
                      : "No hay cargos o capacitaciones que coincidan con la búsqueda."}
                  </td>
                </tr>
              ) : (
                cargosVisibles.map((cargo) => (
                  <tr key={cargo.cargo_id} className="hover:bg-hseq-50/40">
                    <th className="sticky left-0 z-10 border-b border-r border-slate-100 bg-white px-3 py-2 text-left font-medium text-slate-800">
                      {cargo.nombre_cargo}
                    </th>
                    {capsVisibles.map((cap) => {
                      const clave = claveCelda(cargo.cargo_id, cap.capacitacion_id);
                      return (
                        <td key={cap.capacitacion_id} className="border-b border-slate-100 px-2 py-2 text-center">
                          <input
                            type="checkbox"
                            className="h-4 w-4 accent-hseq-800"
                            checked={Boolean(marcas[clave])}
                            disabled={!puedeGuardar || !contextoListo}
                            aria-label={`${cargo.nombre_cargo} × ${cap.codigo}`}
                            onChange={(e) => {
                              const marcada = e.target.checked;
                              setMarcas((prev) => {
                                const siguiente = { ...prev };
                                if (marcada) {
                                  siguiente[clave] = true;
                                } else {
                                  delete siguiente[clave];
                                }
                                return siguiente;
                              });
                            }}
                          />
                        </td>
                      );
                    })}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        </>
      ) : null}
    </>
  );
}
