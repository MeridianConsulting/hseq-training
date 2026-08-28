"use client";

import { RequierePermiso } from "@/components/requiere-permiso";
import { Proximamente } from "@/components/ui/proximamente";

export default function Page() {
  return (
    <RequierePermiso permiso="reportes.ver">
      <Proximamente titulo="Reportes" />
    </RequierePermiso>
  );
}
