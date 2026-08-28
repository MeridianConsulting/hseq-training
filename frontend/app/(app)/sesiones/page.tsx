"use client";

import { RequierePermiso } from "@/components/requiere-permiso";
import { Proximamente } from "@/components/ui/proximamente";

export default function Page() {
  return (
    <RequierePermiso permiso="sesiones.ver">
      <Proximamente titulo="Sesiones y asistencia" />
    </RequierePermiso>
  );
}
