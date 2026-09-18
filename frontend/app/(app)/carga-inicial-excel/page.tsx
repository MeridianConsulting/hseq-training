"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

export default function RedireccionCargaInicialExcel() {
  const router = useRouter();

  useEffect(() => {
    router.replace("/migracion");
  }, [router]);

  return null;
}
