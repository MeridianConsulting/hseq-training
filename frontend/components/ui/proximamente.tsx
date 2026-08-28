"use client";

import { PageHeader } from "@/components/ui/page-header";
import { Card } from "@/components/ui/card";

export function Proximamente({ titulo }: { titulo: string }) {
  return (
    <>
      <PageHeader
        titulo={titulo}
        descripcion="Este módulo está preparado en la navegación y se implementará en una etapa posterior."
      />
      <Card>
        <p className="text-sm text-slate-600">Próximamente.</p>
      </Card>
    </>
  );
}
