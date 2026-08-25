import "dotenv/config";
import path from "node:path";

function required(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Falta la variable de entorno ${name} (ver .env.example)`);
  }
  return value;
}

export const config = {
  arcaCuit: process.env.ARCA_CUIT ?? "",
  arcaClaveFiscal: process.env.ARCA_CLAVE_FISCAL ?? "",
  storageDir: path.resolve(process.env.PLAYWRIGHT_STORAGE_DIR ?? ".sessions"),
  headless: (process.env.PLAYWRIGHT_HEADLESS ?? "false").toLowerCase() === "true",
  ecosistemaBaseUrl: process.env.ECOSISTEMA_BASE_URL ?? "",
  ecosistemaApiKey: process.env.ECOSISTEMA_API_KEY ?? "",
};

export function requireArcaCuit(): string {
  return required("ARCA_CUIT");
}

export function requireEcosistemaConfig(): { baseUrl: string; apiKey: string } {
  return { baseUrl: required("ECOSISTEMA_BASE_URL"), apiKey: required("ECOSISTEMA_API_KEY") };
}
