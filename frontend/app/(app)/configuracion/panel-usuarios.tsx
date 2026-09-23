"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAuth } from "@/components/auth-provider";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { Filters } from "@/components/ui/filters";
import { FiltrosActivos, ListaCargando, type ChipFiltro } from "@/components/ui/filtros-activos";
import { Modal } from "@/components/ui/modal";
import { Pagination } from "@/components/ui/pagination";
import { Table } from "@/components/ui/table";
import { useDebouncedCallback } from "@/hooks/useFiltrosUrl";
import { Pencil, Plus, UserMinus, RotateCcw } from "lucide-react";
import { apiDelete, apiGet, apiPost, apiPut, withQuery, type ListaPaginada } from "@/lib/api";
import type { UsuarioSistema } from "@/lib/tipos";
import { FormularioUsuario, type DatosUsuario } from "./formulario-usuario";

type FiltroEstado = "todos" | "activos" | "inactivos";

const ETIQUETAS: Record<FiltroEstado, string> = {
  todos: "Todos",
  activos: "Activos",
  inactivos: "Inactivos",
};

export function PanelUsuarios({
  onError,
  onMensaje,
}: {
  onError: (m: string | null) => void;
  onMensaje: (m: string | null) => void;
}) {
  const { puede } = useAuth();
  const puedeGestionar = puede("usuarios.gestionar") || puede("catalogos.gestionar");
  const puedeVer = puede("usuarios.ver") || puedeGestionar;
  const [items, setItems] = useState<UsuarioSistema[]>([]);
  const [buscar, setBuscar] = useState("");
  const [filtroEstado, setFiltroEstado] = useState<FiltroEstado>("todos");
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [cargando, setCargando] = useState(false);
  const [abierto, setAbierto] = useState(false);
  const [editando, setEditando] = useState<UsuarioSistema | null>(null);

  async function cargar(
    estado: FiltroEstado = filtroEstado,
    paginaActual = pagina,
    texto = buscar,
  ) {
    setCargando(true);
    const r = await apiGet<ListaPaginada<UsuarioSistema>>(
      withQuery("/api/usuarios", {
        estado,
        buscar: texto || undefined,
        page: paginaActual,
        per_page: 20,
      }),
    );
    setCargando(false);
    if (r.cancelada) return;
    if (!r.success || !r.data) {
      onError(r.message || "No fue posible cargar los usuarios.");
      return;
    }
    setItems(r.data.items);
    setPagina(r.data.pagination.current_page);
    setUltima(r.data.pagination.last_page);
    onError(null);
  }

  useDebouncedCallback(() => {
    void cargar(filtroEstado, 1, buscar);
  }, [filtroEstado, buscar]);

  useEffect(() => {
    void cargar("todos", 1, "");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const chips: ChipFiltro[] = [];
  if (buscar) chips.push({ clave: "buscar", etiqueta: "Buscar", valor: buscar });
  if (filtroEstado !== "todos") {
    chips.push({ clave: "estado", etiqueta: "Estado", valor: ETIQUETAS[filtroEstado] });
  }

  async function guardar(_evento: FormEvent, datos: DatosUsuario) {
    const cuerpo: Record<string, string> = {
      nombre_usuario: datos.nombre_usuario,
      correo: datos.correo,
      estado: datos.estado,
    };
    if (datos.password.trim() !== "") {
      cuerpo.password = datos.password;
    }

    const r = editando
      ? await apiPut<UsuarioSistema>(`/api/usuarios/${editando.usuario_id}`, cuerpo)
      : await apiPost<UsuarioSistema>("/api/usuarios", {
          ...cuerpo,
          password: datos.password,
          estado: "Activo",
        });

    if (r.cancelada) return;
    if (!r.success) {
      onError(r.message || "No se pudo guardar el usuario.");
      return;
    }
    onMensaje(r.message || "Usuario guardado.");
    onError(null);
    setAbierto(false);
    setEditando(null);
    await cargar();
  }

  async function inactivar(item: UsuarioSistema) {
    if (!confirm(`¿Inactivar al usuario ${item.nombre_usuario}?`)) return;
    const r = await apiDelete(`/api/usuarios/${item.usuario_id}`);
    if (r.cancelada) return;
    if (!r.success) {
      onError(r.message || "No se pudo inactivar.");
      return;
    }
    onMensaje(r.message || "Usuario inactivado.");
    onError(null);
    await cargar();
  }

  async function reactivar(item: UsuarioSistema) {
    const r = await apiPut<UsuarioSistema>(`/api/usuarios/${item.usuario_id}`, {
      estado: "Activo",
    });
    if (r.cancelada) return;
    if (!r.success) {
      onError(r.message || "No se pudo reactivar.");
      return;
    }
    onMensaje(r.message || "Usuario reactivado.");
    onError(null);
    await cargar();
  }

  function formatoFecha(valor: string | null): string {
    if (!valor) return "—";
    return valor.slice(0, 19).replace("T", " ");
  }

  if (!puedeVer) {
    return (
      <p className="text-sm text-slate-600">
        No tiene permiso para ver usuarios del sistema.
      </p>
    );
  }

  return (
    <>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-slate-600">
          Alta de cuentas para ingresar al sistema (login). Use esto para los responsables HSEQ.
        </p>
        {puedeGestionar ? (
          <Button
            type="button"
            onClick={() => {
              setEditando(null);
              setAbierto(true);
            }}
          >
            <Plus className="h-4 w-4" aria-hidden />
            Nuevo usuario
          </Button>
        ) : null}
      </div>

      <Filters>
        <Field etiqueta="Buscar">
          <input
            className={inputClass}
            value={buscar}
            onChange={(e) => setBuscar(e.target.value)}
            placeholder="Usuario o correo"
          />
        </Field>
        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={filtroEstado}
            onChange={(e) => setFiltroEstado(e.target.value as FiltroEstado)}
          >
            <option value="todos">Todos</option>
            <option value="activos">Activos</option>
            <option value="inactivos">Inactivos</option>
          </select>
        </Field>
      </Filters>

      <FiltrosActivos
        chips={chips}
        onQuitar={(clave) => {
          if (clave === "buscar") setBuscar("");
          if (clave === "estado") setFiltroEstado("todos");
        }}
        onLimpiar={() => {
          setBuscar("");
          setFiltroEstado("todos");
        }}
      />

      {cargando ? (
        <ListaCargando />
      ) : (
        <Table
          columnas={[
            { clave: "usuario", etiqueta: "Usuario" },
            { clave: "correo", etiqueta: "Correo" },
            { clave: "rol", etiqueta: "Rol" },
            { clave: "estado", etiqueta: "Estado" },
            { clave: "acceso", etiqueta: "Último acceso" },
            { clave: "acciones", etiqueta: "" },
          ]}
          filas={items.map((item) => [
            item.nombre_usuario,
            item.correo,
            item.roles.map((r) => r.nombre).join(", ") || item.rol,
            <Badge key="e" tono={item.estado === "Activo" ? "ok" : "neutral"}>
              {item.estado}
            </Badge>,
            formatoFecha(item.ultimo_acceso),
            <div key="a" className="flex justify-end gap-2">
              {puedeGestionar ? (
                <>
                  <Button
                    type="button"
                    variante="ghost"
                    onClick={() => {
                      setEditando(item);
                      setAbierto(true);
                    }}
                  >
                    <Pencil className="h-4 w-4" aria-hidden />
                    Editar
                  </Button>
                  {item.estado === "Inactivo" ? (
                    <Button type="button" variante="ghost" onClick={() => void reactivar(item)}>
                      <RotateCcw className="h-4 w-4" aria-hidden />
                      Reactivar
                    </Button>
                  ) : (
                    <Button type="button" variante="ghost" onClick={() => void inactivar(item)}>
                      <UserMinus className="h-4 w-4" aria-hidden />
                      Inactivar
                    </Button>
                  )}
                </>
              ) : null}
            </div>,
          ])}
        />
      )}

      <Pagination
        pagina={pagina}
        ultima={ultima}
        onCambiar={(p) => void cargar(filtroEstado, p)}
      />

      <Modal
        abierto={abierto}
        titulo={editando ? "Editar usuario" : "Nuevo usuario del sistema"}
        onCerrar={() => {
          setAbierto(false);
          setEditando(null);
        }}
      >
        <FormularioUsuario
          key={editando ? `u-${editando.usuario_id}` : "nuevo"}
          inicial={editando}
          onCancelar={() => {
            setAbierto(false);
            setEditando(null);
          }}
          onGuardar={guardar}
        />
      </Modal>
    </>
  );
}
