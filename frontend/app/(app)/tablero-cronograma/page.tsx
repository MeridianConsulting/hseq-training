"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

export default function RedireccionTableroCronograma() {
  const router = useRouter();

  useEffect(() => {
    router.replace("/cronograma");
  }, [router]);

  return null;
}
