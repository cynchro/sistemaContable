import { config, requireEcosistemaConfig } from "../config.js";
import type { Libro } from "../types.js";

/**
 * Cliente HTTP de la API de `ecosistema` (backend Modux). Se autentica con
 * una API key (`Authorization: Bearer mk_...`), nunca con usuario/contraseña
 * — el módulo `ApiKeys` ya soporta esto (scopes `iva.compras`/`iva.ventas`/
 * `iva.libro`), y `PermissionMiddleware` resuelve por scope para principals
 * tipo api_key (fix del 24/08/2026, ver `PermissionMiddleware.php`).
 */

const LIBRO_A_RUTA: Record<Libro, "compras" | "ventas"> = { compras: "compras", ventas: "ventas" };

async function llamar(path: string, init: RequestInit = {}): Promise<unknown> {
  const { baseUrl, apiKey } = requireEcosistemaConfig();
  const res = await fetch(`${baseUrl.replace(/\/$/, "")}${path}`, {
    ...init,
    headers: {
      Authorization: `Bearer ${apiKey}`,
      "Content-Type": "application/json",
      ...init.headers,
    },
  });

  const cuerpo = await res.text();
  let json: unknown;
  try {
    json = cuerpo ? JSON.parse(cuerpo) : null;
  } catch {
    throw new Error(`Respuesta no-JSON de ${path} (HTTP ${res.status}): ${cuerpo.slice(0, 300)}`);
  }

  if (!res.ok) {
    const mensaje =
      json && typeof json === "object" && "message" in json
        ? String((json as { message: unknown }).message)
        : cuerpo;
    throw new Error(`${path} → HTTP ${res.status}: ${mensaje}`);
  }

  return json;
}

/**
 * Descarga el TXT de comprobantes o alícuotas del Libro IVA Digital, ya
 * generado por `LibroIvaDigitalWriter` (validado byte a byte contra
 * ejemplos reales de ARCA). `tipo` es "cbte" o "alicuotas".
 */
export async function generarTxtLibroIvaDigital(
  empresaId: number,
  periodoId: number,
  libro: Libro,
  tipo: "cbte" | "alicuotas",
): Promise<string> {
  const { baseUrl, apiKey } = requireEcosistemaConfig();
  const ruta = `${LIBRO_A_RUTA[libro]}-${tipo}`;
  const res = await fetch(
    `${baseUrl.replace(/\/$/, "")}/empresas/${empresaId}/periodos/${periodoId}/libro-iva-digital/${ruta}`,
    { headers: { Authorization: `Bearer ${apiKey}` } },
  );
  const texto = await res.text();
  if (!res.ok) {
    throw new Error(`GET libro-iva-digital/${ruta} → HTTP ${res.status}: ${texto.slice(0, 300)}`);
  }
  return texto;
}

export interface ResultadoImportacion {
  total: number;
  creados: number;
  errores: Array<{ fila: number; error: string }>;
}

/**
 * Sube un lote de comprobantes ya mapeados (ver `mapeo.ts`) vía el
 * importador masivo (`VentaController::import`/`CompraController::import`).
 * Resiliente por diseño del lado del backend: cada fila va en su propia
 * transacción, un error no aborta el resto — por eso el resultado siempre
 * trae `errores` en vez de tirar excepción ante el primer comprobante malo.
 */
export async function importarComprobantes(
  empresaId: number,
  periodoId: number,
  libro: Libro,
  comprobantes: Array<Record<string, unknown>>,
): Promise<ResultadoImportacion> {
  const ruta = LIBRO_A_RUTA[libro];
  const data = await llamar(`/empresas/${empresaId}/periodos/${periodoId}/${ruta}/import`, {
    method: "POST",
    body: JSON.stringify({ comprobantes }),
  });
  const envoltorio = data as { data?: ResultadoImportacion };
  if (!envoltorio.data) {
    throw new Error(`Respuesta inesperada de /${ruta}/import: ${JSON.stringify(data).slice(0, 300)}`);
  }
  return envoltorio.data;
}

export function ecosistemaConfigurado(): boolean {
  return Boolean(config.ecosistemaBaseUrl && config.ecosistemaApiKey);
}

/**
 * Cola del botón "Liquidar IVA" (`app/Modules/Iva/Repositories/LiquidacionRepository.php`,
 * `Iva/routes.php` grupo `/iva/liquidaciones/*`) — consumida por el modo `worker` del bot
 * (`src/cli/worker.ts`). La API key del bot necesita los scopes `iva.liquidaciones.worker` (para
 * `pendiente`/`estado`) y `iva.liquidaciones.credencial` (para `credencialPara`, más estrecho a
 * propósito — separado en el backend para que quede su propio rastro en el audit log).
 */
export interface LiquidacionPendiente {
  id: number;
  empresa_id: number;
  periodo_id: number;
  direccion: "traer" | "subir" | "ambos";
  libro: "ventas" | "compras" | "ambos";
  periodo_arca: string; // MM/YYYY
  cuit: string;
  empresa_nombre: string;
}

/** Toma la siguiente liquidación pendiente del tenant de la API key, o `null` si no hay ninguna. */
export async function tomarSiguientePendiente(): Promise<LiquidacionPendiente | null> {
  const data = await llamar("/iva/liquidaciones/pendientes");
  const envoltorio = data as { data?: { liquidacion: LiquidacionPendiente | null } };
  return envoltorio.data?.liquidacion ?? null;
}

/** Reporta el progreso/resultado de una liquidación ya tomada. */
export async function reportarEstado(
  liquidacionId: number,
  estado: "en_curso" | "terminada" | "error",
  resultado?: unknown,
): Promise<void> {
  await llamar(`/iva/liquidaciones/${liquidacionId}/estado`, {
    method: "POST",
    body: JSON.stringify({ estado, resultado }),
  });
}

/**
 * Clave Fiscal en claro para el login — se llama SOLO cuando `asegurarSesionVigente` detecta que
 * la sesión Playwright de ese CUIT expiró (ver `ClaveFiscalProvider` perezoso en
 * `auth/ensureSession.ts`). Nunca se persiste a disco ni se loguea por consola.
 */
export async function pedirCredencial(liquidacionId: number): Promise<{ cuit: string; clave: string }> {
  const data = await llamar(`/iva/liquidaciones/${liquidacionId}/credencial`, { method: "POST" });
  const envoltorio = data as { data?: { cuit: string; clave: string } };
  if (!envoltorio.data?.clave) {
    throw new Error(`No hay Clave Fiscal cargada para la liquidación ${liquidacionId}.`);
  }
  return envoltorio.data;
}
