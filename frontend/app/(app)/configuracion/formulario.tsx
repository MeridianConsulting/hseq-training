"use client";

import { FormEvent, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, inputClass } from "@/components/ui/field";
import { etiquetaCampoCatalogo } from "@/lib/catalogos";
import type { ItemCatalogo } from "@/lib/tipos";

export function FormularioCatalogo({
  campos,
  permiteActivo,
  inicial,
  onCancelar,
  onGuardar,
}: {
  campos: string[];
  permiteActivo: boolean;
  inicial: ItemCatalogo | null;
  onCancelar: () => void;
  onGuardar: (evento: FormEvent, datos: Record<string, unknown>) => void | Promise<void>;
}) {
  const base = useMemo(() => {
    const datos: Record<string, string> = {};
    for (const campo of campos) {
      datos[campo] = inicial && inicial[campo] != null ? String(inicial[campo]) : "";
    }
    if (permiteActivo) {
      datos.activo = inicial && Number(inicial.activo) === 0 ? "0" : "1";
    }
    return datos;
  }, [campos, inicial, permiteActivo]);

  const [datos, setDatos] = useState(base);

  return (
    <form
      className="grid gap-4"
      onSubmit={(e) => {
        const cuerpo: Record<string, unknown> = { ...datos };
        if (permiteActivo) {
          cuerpo.activo = Number(datos.activo);
        }
        if (cuerpo.cantidad !== undefined && cuerpo.cantidad !== "") {
          cuerpo.cantidad = Number(cuerpo.cantidad);
        }
        void onGuardar(e, cuerpo);
      }}
    >
      {campos.map((campo) => (
        <Field key={campo} etiqueta={etiquetaCampoCatalogo(campo)}>
          {campo === "unidad" ? (
            <select
              className={inputClass}
              value={datos[campo] ?? ""}
              onChange={(e) => setDatos((p) => ({ ...p, [campo]: e.target.value }))}
            >
              <option value="">Seleccione</option>
              <option value="DIAS">Días</option>
              <option value="MESES">Meses</option>
              <option value="ANIOS">Años</option>
            </select>
          ) : (
            <input
              className={inputClass}
              required={campo === "nombre"}
              value={datos[campo] ?? ""}
              onChange={(e) => setDatos((p) => ({ ...p, [campo]: e.target.value }))}
            />
          )}
        </Field>
      ))}
      {permiteActivo ? (
        <Field etiqueta="Estado">
          <select
            className={inputClass}
            value={datos.activo ?? "1"}
            onChange={(e) => setDatos((p) => ({ ...p, activo: e.target.value }))}
          >
            <option value="1">Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </Field>
      ) : null}
      <div className="flex justify-end gap-2">
        <Button type="button" variante="secondary" onClick={onCancelar}>
          Cancelar
        </Button>
        <Button type="submit">Guardar</Button>
      </div>
    </form>
  );
}
