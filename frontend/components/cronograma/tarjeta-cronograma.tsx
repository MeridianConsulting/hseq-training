"use client";

import { useState } from "react";
import Link from "next/link";
import { FormularioSesion, PanelConvocados, tipoModalidad } from "@/app/(app)/cronograma/formulario-sesion";
import { useAuth } from "@/components/auth-provider";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Modal } from "@/components/ui/modal";
import type { ItemCronograma, SesionCronograma } from "@/lib/tipos";

const LIMITE_OBJETIVO = 160;

export function formatoHoras(horas: number | null): string {
  if (horas === null) {
    return "Sin dedicación estimada";
  }

  const entero = Number.isInteger(horas);
  const texto = entero ? String(horas) : horas.toFixed(1).replace(".", ",");
  return `${texto} ${horas === 1 ? "hora" : "horas"}`;
}

function tonoMetodologia(nombre: string | null) {
  if (!nombre) return "neutral" as const;
  const clave = nombre.toUpperCase();
  if (clave.includes("VIRTUAL")) return "bajo" as const;
  if (clave.includes("PRESENCIAL")) return "ok" as const;
  return "aviso" as const;
}

function formatoFecha(valor: string | null): string {
  if (!valor) return "—";
  const [anio, mes, dia] = valor.slice(0, 10).split("-");
  if (!dia) return valor;
  return `${dia}/${mes}/${anio}`;
}

function formatoHora(valor: string | null): string {
  if (!valor) return "";
  const [hh, mm] = valor.split(":");
  const hora = Number(hh);
  if (!Number.isFinite(hora) || !mm) return valor;
  const sufijo = hora >= 12 ? "PM" : "AM";
  const doce = hora % 12 === 0 ? 12 : hora % 12;
  return `${String(doce).padStart(2, "0")}:${mm} ${sufijo}`;
}

function lugarSesion(sesion: SesionCronograma): string {
  const tipo = tipoModalidad(sesion.modalidad_nombre);
  if (tipo === "VIRTUAL") {
    return sesion.enlace_virtual || "Sin enlace";
  }
  if (tipo === "MIXTA") {
    const partes = [sesion.ubicacion_nombre, sesion.enlace_virtual].filter(Boolean);
    return partes.join(" · ") || "Sin ubicación ni enlace";
  }
  return sesion.ubicacion_nombre || "Sin ubicación";
}

export function TarjetaCronograma({
  item,
  onCambio,
}: {
  item: ItemCronograma;
  onCambio?: () => void;
}) {
  const { puede } = useAuth();
  const [abierta, setAbierta] = useState(false);
  const [objetivoCompleto, setObjetivoCompleto] = useState(false);
  const [crearAbierta, setCrearAbierta] = useState(false);
  const [editar, setEditar] = useState<SesionCronograma | null>(null);
  const [convocar, setConvocar] = useState<SesionCronograma | null>(null);
  const objetivoLargo = item.objetivo.length > LIMITE_OBJETIVO;
  const objetivoVisible =
    !objetivoLargo || objetivoCompleto
      ? item.objetivo
      : `${item.objetivo.slice(0, LIMITE_OBJETIVO).trim()}…`;
  const sesiones = item.sesiones ?? [];

  function cerrado() {
    setCrearAbierta(false);
    setEditar(null);
    setConvocar(null);
    onCambio?.();
  }

  return (
    <Card>
      <h3 className="text-base font-semibold text-hseq-900">{item.tema}</h3>
      <p className="mt-1 text-xs text-slate-500">{item.codigo}</p>
      <div className="mt-3 flex flex-wrap items-center gap-2">
        <span className="text-sm text-slate-700">{item.mes_nombre}</span>
        <span className="text-sm text-slate-400" aria-hidden="true">
          ·
        </span>
        <span className="text-sm text-slate-700">{formatoHoras(item.horas)}</span>
        {item.cantidad_programada > 0 ? (
          <Badge tono="neutral">
            {item.cantidad_programada}{" "}
            {item.cantidad_programada === 1 ? "programada" : "programadas"}
          </Badge>
        ) : null}
        <Badge tono={tonoMetodologia(item.metodologia)}>
          {item.metodologia ?? "Sin metodología"}
        </Badge>
        {sesiones.length > 0 ? (
          <Badge tono="ok">
            {sesiones.length} sesión{sesiones.length === 1 ? "" : "es"}
          </Badge>
        ) : null}
      </div>

      {sesiones.length > 0 ? (
        <ul className="mt-4 space-y-2 border-t border-slate-100 pt-4">
          {sesiones.map((sesion) => (
            <li key={sesion.sesion_id} className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
              <p className="text-sm font-medium text-hseq-900">
                {formatoFecha(sesion.fecha)} {formatoHora(sesion.hora)}
              </p>
              <p className="mt-1 text-sm text-slate-700">
                {sesion.modalidad_nombre ?? "Sin modalidad"}
                <span className="text-slate-400"> · </span>
                {lugarSesion(sesion)}
              </p>
              <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                <span>{sesion.proveedor_nombre ?? "Sin proveedor"}</span>
                <span className="text-slate-400">·</span>
                <span>
                  {sesion.convocados} / {sesion.cupo_maximo} convocados
                </span>
                {sesion.cupo_completo ? (
                  <Badge tono="aviso">Cupo completo</Badge>
                ) : (
                  <Badge tono="ok">Cupos disponibles</Badge>
                )}
              </div>
              <div className="mt-2 flex flex-wrap gap-2">
                {puede("sesiones.ver") ? (
                  <Link
                    href={`/sesiones?sesion_id=${sesion.sesion_id}`}
                    className="inline-flex items-center justify-center rounded-lg px-0 py-2 text-sm font-semibold text-hseq-700 hover:bg-hseq-50"
                  >
                    Asistencia
                  </Link>
                ) : null}
                {puede("sesiones.editar") ? (
                  <>
                    <Button type="button" variante="ghost" className="px-0" onClick={() => setEditar(sesion)}>
                      Editar sesión
                    </Button>
                    <Button type="button" variante="ghost" className="px-0" onClick={() => setConvocar(sesion)}>
                      Gestionar convocados
                    </Button>
                  </>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      ) : null}

      {abierta ? (
        <dl className="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm">
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">Tema</dt>
            <dd className="mt-1 text-slate-800">{item.tema}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">Objetivo</dt>
            <dd className="mt-1 whitespace-pre-wrap text-slate-700">{objetivoVisible}</dd>
            {objetivoLargo ? (
              <button
                type="button"
                className="mt-1 text-xs font-medium text-hseq-700 hover:underline"
                onClick={() => setObjetivoCompleto((valor) => !valor)}
              >
                {objetivoCompleto ? "Ver menos" : "Ver más"}
              </button>
            ) : null}
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
                Dedicación estimada
              </dt>
              <dd className="mt-1 text-slate-800">{formatoHoras(item.horas)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
                Metodología
              </dt>
              <dd className="mt-1 text-slate-800">{item.metodologia ?? "Sin metodología"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
                Mes de programación
              </dt>
              <dd className="mt-1 text-slate-800">
                {item.mes_nombre} {item.anio}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">Proceso</dt>
              <dd className="mt-1 text-slate-800">{item.proceso_nombre ?? "Sin proceso"}</dd>
            </div>
          </div>
        </dl>
      ) : null}

      <div className="mt-4 flex flex-wrap gap-3">
        <Button type="button" variante="ghost" className="px-0" onClick={() => setAbierta((v) => !v)}>
          {abierta ? "Ocultar detalles" : "Ver detalles"}
        </Button>
        {puede("sesiones.crear") ? (
          <Button type="button" variante="secondary" onClick={() => setCrearAbierta(true)}>
            Crear sesión
          </Button>
        ) : null}
      </div>

      <Modal abierto={crearAbierta} titulo="Crear sesión" onCerrar={() => setCrearAbierta(false)}>
        <FormularioSesion
          item={item}
          onCancelar={() => setCrearAbierta(false)}
          onGuardado={cerrado}
        />
      </Modal>
      <Modal abierto={editar !== null} titulo="Editar sesión" onCerrar={() => setEditar(null)}>
        {editar ? (
          <FormularioSesion
            item={item}
            sesion={editar}
            onCancelar={() => setEditar(null)}
            onGuardado={cerrado}
          />
        ) : null}
      </Modal>
      <Modal
        abierto={convocar !== null}
        titulo="Gestionar convocados"
        onCerrar={() => setConvocar(null)}
      >
        {convocar ? <PanelConvocados sesionId={convocar.sesion_id} onCambio={onCambio ?? (() => undefined)} /> : null}
      </Modal>
    </Card>
  );
}
