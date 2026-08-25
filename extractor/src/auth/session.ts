import fs from "node:fs";
import path from "node:path";
import { chromium, type Browser, type BrowserContext } from "playwright";
import { config } from "../config.js";

function storageStatePath(cuit: string): string {
  fs.mkdirSync(config.storageDir, { recursive: true });
  return path.join(config.storageDir, `${cuit}.session.json`);
}

export function hasSavedSession(cuit: string): boolean {
  return fs.existsSync(storageStatePath(cuit));
}

/**
 * Abre un browser + context reusando la sesión guardada de ese CUIT si existe.
 * Quien la use es responsable de llamar a `persistSession` después de un login
 * exitoso, y de cerrar el browser al terminar.
 */
export async function openSession(
  cuit: string,
): Promise<{ browser: Browser; context: BrowserContext }> {
  const browser = await chromium.launch({ headless: config.headless });
  const statePath = storageStatePath(cuit);
  const context = await browser.newContext(
    hasSavedSession(cuit) ? { storageState: statePath } : {},
  );
  return { browser, context };
}

export async function persistSession(cuit: string, context: BrowserContext): Promise<void> {
  await context.storageState({ path: storageStatePath(cuit) });
}
