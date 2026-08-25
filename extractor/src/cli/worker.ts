import { hasSavedSession, openSession } from "../auth/session.js";
import { asegurarSesionVigente } from "../auth/ensureSession.js";
import { irAPortalIva, abrirDdjj } from "../flows/portalIva.js";
import { traerLibro, subirLibro, guardarCapturaDeError } from "../liquidacion/ejecutar.js";
import {
  tomarSiguientePendiente,
  reportarEstado,
  pedirCredencial,
  ecosistemaConfigurado,
  type LiquidacionPendiente,
} from "../ecosistema/client.js";
import type { Libro } from "../types.js";

/**
 * Modo `worker` del botón "Liquidar IVA" (plan 25/08/2026): proceso de larga duración (pensado
 * para correr con `docker compose up -d`, no una corrida puntual) que hace polling a la cola de
 * `ecosistema` (`GET /iva/liquidaciones/pendientes`) y ejecuta cada pedido con el mismo flujo ya
 * probado en vivo del CLI `liquidar.ts` (`liquidacion/ejecutar.ts`, compartido).
 *
 * Diferencia clave con `liquidar.ts`: ahí el CUIT es fijo (`.env`, una corrida = un cliente);
 * acá cada liquidación de la cola trae SU PROPIO `cuit` — el worker sirve a todos los clientes
 * del estudio (tenant) de la API key, uno detrás de otro. La Clave Fiscal nunca vive en `.env`
 * acá: se pide al backend (`pedirCredencial`) solo cuando `asegurarSesionVigente` detecta que la
 * sesión Playwright de ESE CUIT puntual expiró (proveedor perezoso, ver `ClaveFiscalProvider`).
 */

const INTERVALO_POLLING_MS = 15_000;

function librosDe(libro: LiquidacionPendiente["libro"]): Libro[] {
  return libro === "ambos" ? ["ventas", "compras"] : [libro];
}

async function procesar(pendiente: LiquidacionPendiente): Promise<void> {
  const { id, cuit, empresa_id: empresaId, periodo_id: periodoId, periodo_arca: periodoArca, direccion, libro } =
    pendiente;

  if (!hasSavedSession(cuit)) {
    await reportarEstado(id, "error", {
      mensaje:
        `No hay sesión de ARCA guardada para CUIT ${cuit}. Alguien tiene que correr ` +
        '"npm run login" una vez, a mano, para este CUIT antes de poder automatizarlo (ver README).',
    });
    console.error(`[worker] Liquidación ${id}: sin sesión guardada para CUIT ${cuit}, saltada.`);
    return;
  }

  await reportarEstado(id, "en_curso");

  const { browser, context } = await openSession(cuit);
  let page = await context.newPage();
  const resultado: Record<string, unknown> = {};

  try {
    // Perezoso a propósito: solo pega contra el backend a pedir la clave si la sesión de ESTE
    // CUIT realmente expiró — la mayoría de las corridas reusan la sesión sin necesitarla.
    await asegurarSesionVigente(page, context, cuit, () => pedirCredencial(id).then((c) => c.clave));

    console.log(`[worker] Abriendo Portal IVA (${cuit}, período ${periodoArca})...`);
    page = await irAPortalIva(page);
    await abrirDdjj(page, periodoArca);

    for (const l of librosDe(libro)) {
      const porLibro: Record<string, unknown> = {};
      if (direccion === "traer" || direccion === "ambos") {
        porLibro.traer = await traerLibro(page, l, empresaId, periodoId);
      }
      if (direccion === "subir" || direccion === "ambos") {
        porLibro.subir = await subirLibro(page, l, empresaId, periodoId);
      }
      resultado[l] = porLibro;
    }

    await reportarEstado(id, "terminada", resultado);
    console.log(`[worker] Liquidación ${id} terminada.`);
  } catch (err) {
    await guardarCapturaDeError(page);
    const mensaje = err instanceof Error ? err.message : String(err);
    await reportarEstado(id, "error", { mensaje, parcial: resultado });
    console.error(`[worker] Liquidación ${id} falló:`, err);
  } finally {
    await browser.close();
  }
}

async function main() {
  if (!ecosistemaConfigurado()) {
    throw new Error("Falta ECOSISTEMA_BASE_URL / ECOSISTEMA_API_KEY en .env (ver .env.example).");
  }

  let seguir = true;
  process.on("SIGINT", () => (seguir = false));
  process.on("SIGTERM", () => (seguir = false));

  console.log(`[worker] Arrancando, polling cada ${INTERVALO_POLLING_MS / 1000}s...`);

  // Loop infinito a propósito: proceso de larga duración (docker compose up -d), no una corrida
  // puntual. Un error de UN pedido (capturado dentro de procesar()) no debe tirar el worker
  // entero — solo un error hablando con ecosistema en sí (red/infra) cae acá y se loguea.
  while (seguir) {
    try {
      const pendiente = await tomarSiguientePendiente();
      if (pendiente) {
        console.log(
          `[worker] Tomada liquidación ${pendiente.id} (${pendiente.empresa_nombre}, CUIT ${pendiente.cuit}).`,
        );
        await procesar(pendiente);
        continue; // drena la cola sin esperar el intervalo si había más trabajo.
      }
    } catch (err) {
      console.error("[worker] Error en el ciclo de polling:", err);
    }

    await new Promise((resolve) => setTimeout(resolve, INTERVALO_POLLING_MS));
  }

  console.log("[worker] Apagado por señal, saliendo.");
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
