import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // El proxy de /api vive en app/api/[...ruta]/route.ts para reenviar Authorization.
  async redirects() {
    return [
      {
        source: "/tablero-cronograma",
        destination: "/cronograma",
        permanent: false,
      },
    ];
  },
};

export default nextConfig;
