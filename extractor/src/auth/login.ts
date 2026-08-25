import type { Page } from "playwright";

const ARCA_LOGIN_URL = "https://auth.afip.gob.ar/contribuyente_/login.xhtml";
const LOGIN_DOMAIN = "auth.afip.gob.ar";
const POST_LOGIN_TIMEOUT_MS = 20_000;

export class LoginNoConfirmadoError extends Error {}

/**
 * Login contra el portal de Clave Fiscal de ARCA. Pensado para correr tanto
 * headed (una persona mirando, primera vez / cuando ARCA pide verificación
 * extra) como headless y automatizado (Docker, reusando .env — a pedido del
 * usuario, ver README §Login).
 *
 * ⚠️ Selectores *históricos* del formulario de AFIP/ARCA (id="F1:username" /
 * "F1:btnSiguiente" / "F1:password" / "F1:btnIngresar") — no verificados
 * contra el portal real todavía (en la exploración en vivo el usuario se
 * logueó él mismo a mano en su propio browser; el asistente nunca completó
 * este formulario). Puede necesitar ajustes la primera vez que se corra.
 *
 * Importante en modo headless/automatizado: si ARCA pide una verificación
 * adicional (SMS, pregunta de seguridad — típico en un dispositivo/sesión
 * "nueva"), esta función NO puede resolverla — no hay nadie mirando. En vez
 * de colgarse en silencio, detecta que no salió del dominio de login dentro
 * del timeout y tira `LoginNoConfirmadoError` con un mensaje claro: correr
 * con PLAYWRIGHT_HEADLESS=false esa vez para resolverlo a mano.
 */
export async function login(page: Page, cuit: string, claveFiscal: string): Promise<void> {
  await page.goto(ARCA_LOGIN_URL);

  await page.fill("#F1\\:username", cuit);
  await page.click("#F1\\:btnSiguiente");

  await page.waitForSelector("#F1\\:password", { state: "visible" });
  await page.fill("#F1\\:password", claveFiscal);
  await page.click("#F1\\:btnIngresar");

  try {
    await page.waitForURL((url) => !url.hostname.includes(LOGIN_DOMAIN), {
      timeout: POST_LOGIN_TIMEOUT_MS,
    });
  } catch {
    throw new LoginNoConfirmadoError(
      `El login no salió de ${LOGIN_DOMAIN} después de ${POST_LOGIN_TIMEOUT_MS / 1000}s. ` +
        "Probablemente ARCA está pidiendo una verificación adicional (SMS, pregunta de " +
        "seguridad) que solo se puede resolver a mano. Corré \"npm run login\" con " +
        "PLAYWRIGHT_HEADLESS=false (fuera de Docker) para resolverla una vez; las corridas " +
        "siguientes van a reusar la sesión guardada sin pedir esto de nuevo.",
    );
  }

  await page.waitForLoadState("networkidle");
}
