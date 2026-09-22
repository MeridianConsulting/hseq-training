/**
 * Build estatico de produccion.
 * Fuerza NEXT_PUBLIC_API_URL desde .env.production para que .env.local
 * (localhost) no quede incrustado en el export.
 */
import { spawn } from "node:child_process";
import { readFileSync, existsSync } from "node:fs";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const prodEnvPath = resolve(root, ".env.production");

if (!existsSync(prodEnvPath)) {
  console.error("Falta frontend/.env.production con NEXT_PUBLIC_API_URL de produccion.");
  process.exit(1);
}

const texto = readFileSync(prodEnvPath, "utf8");
const match = texto.match(/^\s*NEXT_PUBLIC_API_URL\s*=\s*(.+)\s*$/m);
const apiUrl = match ? match[1].trim().replace(/^["']|["']$/g, "") : "";

if (!apiUrl || !/^https?:\/\//i.test(apiUrl)) {
  console.error("NEXT_PUBLIC_API_URL en .env.production debe ser una URL http(s) absoluta.");
  process.exit(1);
}

console.log(`Build produccion → NEXT_PUBLIC_API_URL=${apiUrl}`);

const hijo = spawn("npx", ["next", "build"], {
  cwd: root,
  stdio: "inherit",
  shell: true,
  env: {
    ...process.env,
    NEXT_PUBLIC_API_URL: apiUrl,
    NODE_ENV: "production",
  },
});

hijo.on("exit", (code) => process.exit(code ?? 1));
