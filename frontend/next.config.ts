import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Sitio 100% estático: el hosting de producción no tiene Node.js,
  // solo Apache/PHP. Sin proxy /api (llamadas directas a NEXT_PUBLIC_API_URL)
  // y sin redirects() (no soportado en output: "export").
  output: "export",
  distDir: "build",
  trailingSlash: true,
  // Sin servidor no hay endpoint /_next/image; next/image debe usar los
  // archivos originales tal cual.
  images: {
    unoptimized: true,
  },
};

export default nextConfig;
