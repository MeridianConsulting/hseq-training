"use client";

import { FormEvent, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import type { UsuarioSistema } from "@/lib/tipos";

export type DatosUsuario = {
  nombre_usuario: string;
  correo: string;
  password: string;
  estado: "Activo" | "Inactivo";
};

type Props = {
  inicial?: UsuarioSistema | null;
  onCancelar: () => void;
  onGuardar: (evento: FormEvent, datos: DatosUsuario) => void | Promise<void>;
};

export function FormularioUsuario({ inicial, onCancelar, onGuardar }: Props) {
  const esEdicion = Boolean(inicial);
  const [nombre, setNombre] = useState(inicial?.nombre_usuario ?? "");
  const [correo, setCorreo] = useState(inicial?.correo ?? "");
  const [password, setPassword] = useState("");
  const [estado, setEstado] = useState<"Activo" | "Inactivo">(
    inicial?.estado === "Inactivo" ? "Inactivo" : "Activo",
  );
  const [enviando, setEnviando] = useState(false);

  async function submit(evento: FormEvent) {
    evento.preventDefault();
    setEnviando(true);
    try {
      await onGuardar(evento, {
        nombre_usuario: nombre.trim(),
        correo: correo.trim(),
        password,
        estado,
      });
    } finally {
      setEnviando(false);
    }
  }

  return (
    <form className="space-y-4" onSubmit={(e) => void submit(e)}>
      <p className="text-sm text-slate-600">
        Los usuarios creados aquí pueden iniciar sesión en el sistema HSEQ con rol Administrador
        HSEQ (acceso completo). No son trabajadores del maestro de personal.
      </p>
      <Field etiqueta="Nombre de usuario">
        <input
          className={inputClass}
          value={nombre}
          onChange={(e) => setNombre(e.target.value)}
          required
          maxLength={50}
          autoComplete="off"
          placeholder="ej. jperez"
        />
      </Field>
      <Field etiqueta="Correo">
        <input
          className={inputClass}
          type="email"
          value={correo}
          onChange={(e) => setCorreo(e.target.value)}
          required
          maxLength={100}
          autoComplete="off"
          placeholder="correo@empresa.com"
        />
      </Field>
      <Field etiqueta={esEdicion ? "Nueva contraseña (opcional)" : "Contraseña"}>
        <input
          className={inputClass}
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required={!esEdicion}
          minLength={esEdicion && password === "" ? undefined : 8}
          maxLength={120}
          autoComplete="new-password"
        />
      </Field>
      <p className="text-xs text-slate-500">
        {esEdicion
          ? "Déjela vacía para no cambiarla. Si la cambia, mínimo 8 caracteres."
          : "Mínimo 8 caracteres."}
      </p>
      {esEdicion ? (
        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={estado}
            onChange={(e) => setEstado(e.target.value as "Activo" | "Inactivo")}
          >
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
        </Field>
      ) : null}
      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variante="secondary" onClick={onCancelar} disabled={enviando}>
          Cancelar
        </Button>
        <Button type="submit" disabled={enviando}>
          {enviando ? "Guardando…" : esEdicion ? "Guardar cambios" : "Crear usuario"}
        </Button>
      </div>
    </form>
  );
}
