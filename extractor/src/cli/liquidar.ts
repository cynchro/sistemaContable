import { config, requireArcaCuit } from "../config.js";
import { hasSavedSession, openSession } from "../auth/session.js";
import { asegurarSesionVigente } from "../auth/ensureSession.js";
import { irAPortalIva, abrirDdjj } from "../flows/portalIva.js";
import { traerLibro, subirLibro, guardarCapturaDeError } from "../liquidacion/ejecutar.js";
import { ecosistemaConfigurado } from "../ecosistema/client.js";
import type { Libro, PeriodoFiscal } from "../types.js";

/**
 * Orquestador de las dos direcciones del "botón Liquidar IVA" (ver plan del 24-25/08/2026):
 * traer lo que ARCA ya tiene registrado hacia `ecosistema`, y/o subir lo que `ecosistema` ya
 * calculó hacia el borrador de ARCA. Ambas direcciones fueron probadas en vivo a mano el
 * 24/08/2026 (cuenta real CUIT 20145415021) antes de escribir este orquestador — no hay pasos
 * acá que no se hayan visto funcionar antes. `traerLibro`/`subirLibro` viven en
 * `liquidacion/ejecutar.ts`, compartidas con `cli/worker.ts` (modo automático, consume la cola
 * de `ecosistema` en vez de recibir los flags por línea de comando).
 *
 * Lo que NO hace, a propósito: nunca toca "Presentar/Confirmar presentación" (ver allowlist en
 * flows/portalIva.ts). Este script deja todo en el borrador — presentar la DDJJ sigue siendo una
 * decisión humana explícita.
 */

interface Args {
  periodoArca: PeriodoFiscal;
  empresaId: number;
  periodoEcosistemaId: number;
  traer: boolean;
  subir: boolean;
  libros: Libro[];
}

function parseArgs(): Args {
  const args = process.argv.slice(2);
  const valor = (flag: string): string | undefined => {
    const idx = args.indexOf(flag);
    return idx >= 0 ? args[idx + 1] : undefined;
  };

  const periodoArca = valor("--periodo");
  const empresaId = valor("--empresa");
  const periodoEcosistemaId = valor("--periodo-eco");
  const libroArg = valor("--libro") ?? "ambos";

  if (!periodoArca || !empresaId || !periodoEcosistemaId) {
    throw new Error(
      "Uso: npm run liquidar -- --periodo MM/YYYY --empresa <id ecosistema> " +
        "--periodo-eco <id periodo ecosistema> [--traer] [--subir] [--libro ventas|compras|ambos]\n" +
        "Al menos uno de --traer / --subir es requerido.",
    );
  }

  const traer = args.includes("--traer");
  const subir = args.includes("--subir");
  if (!traer && !subir) {
    throw new Error("Especificá --traer y/o --subir.");
  }

  const libros: Libro[] = libroArg === "ambos" ? ["ventas", "compras"] : [libroArg as Libro];

  return {
    periodoArca,
    empresaId: Number(empresaId),
    periodoEcosistemaId: Number(periodoEcosistemaId),
    traer,
    subir,
    libros,
  };
}

async function main() {
  const args = parseArgs();
  const cuit = requireArcaCuit();

  if (!ecosistemaConfigurado()) {
    throw new Error("Falta ECOSISTEMA_BASE_URL / ECOSISTEMA_API_KEY en .env (ver .env.example).");
  }
  if (!hasSavedSession(cuit)) {
    throw new Error(`No hay sesión guardada para CUIT ${cuit}. Corré "npm run login" primero.`);
  }

  const { browser, context } = await openSession(cuit);
  let paginaActual = await context.newPage();

  try {
    await asegurarSesionVigente(paginaActual, context, cuit, config.arcaClaveFiscal || undefined);

    console.log(`Abriendo Portal IVA, período ${args.periodoArca}...`);
    paginaActual = await irAPortalIva(paginaActual);
    await abrirDdjj(paginaActual, args.periodoArca);

    for (const libro of args.libros) {
      console.log(`\n=== ${libro.toUpperCase()} ===`);
      if (args.traer) {
        await traerLibro(paginaActual, libro, args.empresaId, args.periodoEcosistemaId);
      }
      if (args.subir) {
        await subirLibro(paginaActual, libro, args.empresaId, args.periodoEcosistemaId);
        console.log(
          "  Revisar 'Historial de Importaciones' en el navegador para el detalle fila por fila.",
        );
      }
    }

    console.log(
      "\nListo. El borrador en ARCA sigue sin presentar — revisalo en el navegador antes de " +
        "cualquier paso posterior.",
    );
  } catch (err) {
    await guardarCapturaDeError(paginaActual);
    throw err;
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
