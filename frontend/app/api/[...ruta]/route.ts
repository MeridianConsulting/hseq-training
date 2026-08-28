import { NextRequest, NextResponse } from "next/server";

/**
 * Proxy al backend PHP. Las rewrites de next.config no reenvian Authorization,
 * y eso provocaba 401 al entrar (el token se borraba y volvia al login).
 */
const ORIGEN = (process.env.API_ORIGIN ?? "http://localhost:8080").replace(/\/$/, "");

async function proxy(request: NextRequest, segmentos: string[]): Promise<NextResponse> {
  const destino = `${ORIGEN}/api/${segmentos.join("/")}${request.nextUrl.search}`;
  const headers = new Headers();

  const autorizacion = request.headers.get("authorization");
  if (autorizacion) {
    headers.set("Authorization", autorizacion);
  }

  const aceptar = request.headers.get("accept");
  if (aceptar) {
    headers.set("Accept", aceptar);
  }

  const tipo = request.headers.get("content-type");
  if (tipo) {
    headers.set("Content-Type", tipo);
  }

  const metodo = request.method.toUpperCase();
  const cuerpo = metodo === "GET" || metodo === "HEAD" ? undefined : await request.arrayBuffer();

  const upstream = await fetch(destino, {
    method: metodo,
    headers,
    body: cuerpo,
    cache: "no-store",
    redirect: "manual",
  });

  const respuesta = new NextResponse(await upstream.arrayBuffer(), {
    status: upstream.status,
  });

  const contentType = upstream.headers.get("content-type");
  if (contentType) {
    respuesta.headers.set("content-type", contentType);
  }

  return respuesta;
}

type Contexto = { params: Promise<{ ruta: string[] }> };

export async function GET(request: NextRequest, contexto: Contexto) {
  return proxy(request, (await contexto.params).ruta);
}

export async function POST(request: NextRequest, contexto: Contexto) {
  return proxy(request, (await contexto.params).ruta);
}

export async function PUT(request: NextRequest, contexto: Contexto) {
  return proxy(request, (await contexto.params).ruta);
}

export async function PATCH(request: NextRequest, contexto: Contexto) {
  return proxy(request, (await contexto.params).ruta);
}

export async function DELETE(request: NextRequest, contexto: Contexto) {
  return proxy(request, (await contexto.params).ruta);
}

export async function OPTIONS(request: NextRequest, contexto: Contexto) {
  return proxy(request, (await contexto.params).ruta);
}
