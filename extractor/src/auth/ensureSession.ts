import type { Page, BrowserContext } from "playwright";
import { login } from "./login.js";
import { persistSession } from "./session.js";

const PORTAL_HOME = "https://portalcf.cloud.afip.gob.ar/portal/app/";

/**
 * ARCA invalida la sesión bastante rápido (confirmado en vivo: ~42 minutos
 * después de loguear, la sesión guardada ya redirigía a
 * `.../expiredSession`) — no alcanza con "loguear una vez y reusar para
 * siempre". Esta función chequea si la sesión sigue viva navegando al home
 * del portal; si ARCA la marca como expirada, vuelve a loguear (necesita
 * `claveFiscal` — si no está disponible, tira un error claro en vez de
 * quedarse trabada) y persiste la sesión refrescada para la próxima corrida.
 */
/**
 * `claveFiscal` acepta un valor fijo (CLI `liquidar.ts`, un solo CUIT por `.env`) o un
 * proveedor async perezoso (worker `worker.ts`, muchos CUITs distintos por corrida) — el
 * proveedor solo se invoca si la sesión REALMENTE expiró, para no pedir la Clave Fiscal al
 * backend (`POST /iva/liquidaciones/{id}/credencial`) en cada corrida sin necesidad.
 */
export type ClaveFiscalProvider = string | undefined | (() => Promise<string | undefined>);

export async function asegurarSesionVigente(
  page: Page,
  context: BrowserContext,
  cuit: string,
  claveFiscal: ClaveFiscalProvider,
): Promise<void> {
  await page.goto(PORTAL_HOME);
  await page.waitForLoadState("networkidle");

  const expirada = page.url().includes("expiredSession") || page.url().includes("login.xhtml");
  if (!expirada) return;

  const clave = typeof claveFiscal === "function" ? await claveFiscal() : claveFiscal;
  if (!clave) {
    throw new Error(
      "La sesión guardada expiró y no hay Clave Fiscal disponible para renovarla sola. " +
        'Corré "npm run login" de nuevo para este CUIT.',
    );
  }

  console.log("La sesión guardada expiró — logueando de nuevo antes de seguir...");
  await login(page, cuit, clave);
  await persistSession(cuit, context);
  console.log("Sesión renovada.");
}
