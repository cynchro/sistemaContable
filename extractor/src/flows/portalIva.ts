import type { Page } from "playwright";
import type { Libro, PeriodoFiscal } from "../types.js";

/**
 * ALLOWLIST DE ACCIONES — regla de diseño no negociable (ver plan de
 * factibilidad, sección "Límite duro"): este archivo solo debe contener
 * funciones que naveguen/importen/consulten. Ninguna función de acá debe
 * clickear nada de la familia "Presentar" / "Confirmar presentación" /
 * "Generar DDJJ definitiva". Si en algún momento hace falta tocar uno de
 * esos botones para algo, PARAR y preguntar — no se agrega solo.
 *
 * Todos los selectores de este archivo fueron confirmados contra el DOM
 * real en la sesión de exploración del 2026-07-26 (cuenta de Responsable
 * Inscripto, borrador YA EXISTENTE para el período 07/2026). Queda sin
 * confirmar el camino "período sin borrador previo" (pantalla "Datos
 * Iniciales / CON MOVIMIENTOS") — `abrirDdjj` lo detecta y tira un error
 * claro en vez de asumir un comportamiento no verificado.
 */

/**
 * Desde el home del Portal de Clave Fiscal, abre "Portal IVA". Devuelve la
 * Page de la nueva pestaña que abre ARCA (no navega la pestaña original) —
 * los pasos siguientes (`abrirDdjj`, `irALibro`, extracción) se hacen sobre
 * la Page devuelta.
 */
export async function irAPortalIva(page: Page): Promise<Page> {
  await page.goto("https://portalcf.cloud.afip.gob.ar/portal/app/");

  // Por CSS + texto, no por rol/nombre accesible: getByRole con exact:true
  // falló en una corrida real (el link apareció en pantalla — confirmado
  // por captura — pero el nombre accesible computado no matcheaba "Portal
  // IVA" exacto, probablemente por contenido extra dentro del <a>, ej. un
  // ícono). `a.full-width` + texto es lo que se validó contra el DOM real.
  const link = page.locator("a.full-width").filter({ hasText: "Portal IVA" }).first();
  // La sección "Servicios | Más utilizados" carga de forma asíncrona (se ve
  // un spinner en la carga inicial, confirmado en la exploración en vivo) —
  // hay que esperar a que aparezca, no alcanza con chequear apenas navega.
  const aparecio = await link
    .waitFor({ state: "visible", timeout: 15_000 })
    .then(() => true)
    .catch(() => false);

  // TODO no confirmado en vivo: si la cuenta no tiene "Portal IVA" adherido
  // todavía (no aparece en "Más utilizados"), hace falta buscar por texto
  // (#buscadorInput) y puede aparecer el modal "Agregar Servicio" — no
  // clickear "Continuar" ahí sin que el usuario lo vea primero.
  if (!aparecio) {
    throw new Error(
      'No se encontró el link "Portal IVA" en "Más utilizados" después de 15s — la cuenta ' +
        "puede no tenerlo adherido todavía. Ese camino (buscar + Agregar Servicio) no está " +
        "implementado, requiere confirmación en vivo antes de automatizarlo.",
    );
  }

  const [popup] = await Promise.all([page.waitForEvent("popup"), link.click()]);
  await popup.waitForLoadState();
  return popup;
}

function periodoAValorSelect(periodo: PeriodoFiscal): string {
  const [mes, anio] = periodo.split("/");
  if (!mes || !anio || mes.length > 2 || anio.length !== 4) {
    throw new Error(`Período inválido: "${periodo}" (se espera "MM/YYYY", ej. "07/2026")`);
  }
  return `${anio}${mes.padStart(2, "0")}`;
}

/**
 * Abre la DDJJ del período indicado: "Nueva declaración jurada" → seleccionar
 * período → Continuar → "Registración y declaración" → Ingresar. Deja `page`
 * navegada (mismo tab, cambia de dominio a liva.afip.gob.ar) en el menú
 * "Libro IVA | {periodo}" con las tarjetas Libro Ventas / Libro Compras.
 *
 * Confirmado en vivo: si ya existe un borrador para el período, ARCA lo
 * reabre directo (sin pasar por "Datos Iniciales / CON MOVIMIENTOS"). Si no
 * existe borrador, ese camino no está confirmado — se detecta por URL y se
 * tira un error explícito en vez de improvisar.
 */
export async function abrirDdjj(page: Page, periodo: PeriodoFiscal): Promise<void> {
  // Por texto de DOM (locator+hasText), no por getByRole/nombre accesible:
  // esta app tiene botones con aria-label de keys de i18n sin traducir que
  // pisan el texto visible en el cálculo de nombre accesible (confirmado en
  // otro botón "Ingresar" de esta misma pantalla — ver botonRegistracion
  // abajo) — getByRole con esos botones no encuentra nada aunque estén
  // visibles, como pasó en una corrida real.
  await page.locator("button").filter({ hasText: "Ingresar" }).first().click();
  await page.waitForURL((url) => url.hash.includes("nueva.declaracion.jurada"));

  await page.selectOption("#periodo", periodoAValorSelect(periodo));
  await page.locator("button").filter({ hasText: "Continuar" }).first().click();

  // Después de "Continuar" puede aparecer la pantalla "Datos Iniciales"
  // (período sin borrador previo — NO confirmado en vivo) o ir directo a
  // las tarjetas "Registración y declaración" (con borrador existente —
  // confirmado). Se detecta por la presencia del botón de la tarjeta.
  const botonRegistracion = page.locator('[aria-label*="iva.btn.home.liva.alt"]');
  const aparecio = await botonRegistracion
    .waitFor({ state: "visible", timeout: 15_000 })
    .then(() => true)
    .catch(() => false);

  if (!aparecio) {
    throw new Error(
      'No apareció la tarjeta "Registración y declaración" después de Continuar — ' +
        'probablemente cayó en la pantalla "Datos Iniciales / CON MOVIMIENTOS" ' +
        "(período sin borrador previo), camino no confirmado en vivo todavía.",
    );
  }

  await Promise.all([
    page.waitForURL((url) => url.hostname === "liva.afip.gob.ar"),
    botonRegistracion.click(),
  ]);
}

const RUTA_LIBRO: Record<Libro, string> = {
  ventas: "verVentas.do?t=31",
  compras: "verCompras.do?t=21",
};

/**
 * Navega a la solapa del libro indicado (asume que `abrirDdjj` ya se
 * ejecutó). Por URL directa, no por click en `#btnLibroVentas`/
 * `#btnLibroCompras` — esos botones solo existen en la pantalla intermedia
 * del menú (las 3 tarjetas); si ya se está adentro de un libro (ej. se
 * acaba de extraer Ventas y toca pasar a Compras) esos botones no están en
 * la página, hay un botón "CONTINUAR AL LIBRO X" distinto en su lugar. La
 * URL directa funciona sin importar desde dónde se llame (confirmado en
 * vivo: `t=31`/`t=21` son códigos fijos de sección, no cambian por período).
 */
export async function irALibro(page: Page, libro: Libro): Promise<void> {
  const url = new URL(RUTA_LIBRO[libro], page.url());
  await page.goto(url.toString());
  await page.waitForLoadState("networkidle");
}

/**
 * Abre el modal "Importar desde ARCA...", deja el filtro de fechas tal como
 * lo preselecciona ARCA (rango del período) y confirma. NO espera a que
 * termine el job — eso lo hace `esperarImportacion`
 * (flows/esperarImportacion.ts).
 *
 * ✅ Confirmado en vivo (24/08/2026, cuenta real CUIT 20145415021, período
 * 08/2026): el click de confirmación SÍ se probó — trajo 3 comprobantes
 * electrónicos reales al borrador ("Comprobantes Electrónicos encontrados: 3,
 * Agregados: 3"). Es de solo lectura respecto a ARCA (nunca presenta/confirma
 * nada), y con la opción por defecto "Conservar el comprobante previamente
 * incluído" no pisa lo que ya estaba en el borrador.
 */
export async function importarDesdeArca(page: Page, _libro: Libro): Promise<void> {
  await page.click("#btnDropdownImportar");
  await page.click("#lnkImportarAFIP");
  await page.waitForSelector("#btnImportarAFIPImportar", { state: "visible" });
  await page.click("#btnImportarAFIPImportar");
}

/**
 * Abre el modal "Importar Archivos...", carga los dos TXT (comprobantes +
 * alícuotas, formato Libro IVA Digital ya generado por `ecosistema`) y
 * confirma. Dirección opuesta a `importarDesdeArca`: esto SÍ escribe en el
 * borrador de ARCA (agrega comprobantes), aunque sigue sin tocar nunca la
 * familia "Presentar/Confirmar presentación" — la misma línea roja de
 * siempre.
 *
 * ✅ Confirmado en vivo (24/08/2026, misma cuenta/período): subida real de 2
 * compras + 2 ventas, ambas "Procesada" con "Agregados" = la cantidad
 * subida. También se confirmó en vivo que ARCA valida por fila (ej. rechazó
 * un CUIT sin dígito verificador válido con un error legible) — el llamador
 * debe leer el resultado de `esperarImportacion` y no asumir éxito.
 *
 * Selectores confirmados contra el DOM real (24/08/2026): el link del menú
 * es `#lnkImportarArchivo` (singular — distinto de `#lnkImportarAFIP`), y
 * los dos `<input type=file>` son genéricos (`#archivo`/`#archivoIVA`, sin
 * distinguir ventas/compras — el `libro` ya lo fija el contexto de la
 * página). El botón final "Importar" del modal tiene un id opaco/generado
 * (no legible desde el DOM estático) y coexiste con un botón homónimo del
 * modal "Importar desde ARCA" — se ubica por rol+texto dentro del último
 * diálogo visible, no por id, para no depender de ese valor.
 *
 * @param archivoCbte      Ruta al TXT de comprobantes (compras-cbte o
 *                          ventas-cbte, según `libro`).
 * @param archivoAlicuotas Ruta al TXT de alícuotas correspondiente.
 */
/**
 * Cierra el diálogo de resultados que ARCA abre solo tras confirmar
 * "Importar desde ARCA..." o "Importar Archivos..." (el mismo modal
 * "Historial de Importaciones", con el resumen "Agregados/Errores" arriba).
 *
 * ⚠️ Encontrado en vivo (24/08/2026, primera corrida real de `npm run
 * liquidar`): si no se cierra, tapa la grilla y el siguiente paso (extraer
 * el CSV con el botón "CSV" de `.dt-buttons`) tira timeout esperando el
 * evento "download" — el click no llega al botón real, capturado por el
 * modal superpuesto. No-op si no hay ningún diálogo visible.
 */
export async function cerrarDialogoResultados(page: Page): Promise<void> {
  const dialogoVisible = page.locator('[role="dialog"]:visible, .mat-dialog-container:visible').last();
  const hayDialogo = await dialogoVisible
    .waitFor({ state: "visible", timeout: 3_000 })
    .then(() => true)
    .catch(() => false);
  if (!hayDialogo) return;

  await dialogoVisible.getByRole("button", { name: "Cerrar", exact: true }).click();
  await dialogoVisible.waitFor({ state: "hidden", timeout: 5_000 }).catch(() => {});
}

export async function importarArchivos(
  page: Page,
  _libro: Libro,
  archivoCbte: string,
  archivoAlicuotas: string,
): Promise<void> {
  await page.click("#btnDropdownImportar");
  await page.click("#lnkImportarArchivo");

  await page.waitForSelector("#archivo", { state: "visible" });
  await page.setInputFiles("#archivo", archivoCbte);
  await page.setInputFiles("#archivoIVA", archivoAlicuotas);

  const dialogoVisible = page.locator('[role="dialog"]:visible, .mat-dialog-container:visible').last();
  await dialogoVisible.getByRole("button", { name: "Importar", exact: true }).click();
}
