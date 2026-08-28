"use client";

import { PageHeader } from "@/components/ui/page-header";
import { Card } from "@/components/ui/card";

export default function SinAccesoPage() {
  return (
    <>
      <PageHeader titulo="Sin acceso" descripcion="Su usuario no tiene permisos para el módulo solicitado." />
      <Card>
        <p className="text-sm text-slate-600">
          Si considera que debería ver esta sección, solicite al administrador HSEQ la revisión de su rol.
        </p>
      </Card>
    </>
  );
}
