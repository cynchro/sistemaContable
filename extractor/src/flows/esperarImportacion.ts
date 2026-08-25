import type { Page } from "playwright";
import type { Libro } from "../types.js";

const CODIGO_LIBRO: Record<Libro, number> = { compras: 21, ventas: 31 };

/**
 * Job de `ajax.do?f=listaTareas` (Portal IVA). Confirmado en vivo (25/08/2026) leyendo la
 * respuesta cruda completa: trae bastante más que `estado`/`progreso` — el detalle real de la
 * importación (agregados/erróneos/registros/errores por fila) está acá, no en otro endpoint
 * separado (se probaron `detalleTarea`/`verDetalleTarea`/`listaErroresTarea`/
 * `detalleImportacion`, los 4 responden 200 vacío — no existen).
 */
export interface TareaImportacion {
  codigo: number;
  estado: string; // "TE" = Terminada (confirmado en vivo)
  progreso: number; // 0-100, -1 cuando ARCA todavía no lo calculó
  progresoActualEstimado: number | null;
  fechaCreacion: string;
  fechaInicioProc: string;
  fechaFinProc: string;
  cantidadAgregados: number;
  cantidadErroneos: number;
  cantidadIgnorados: number;
  cantidadModificados: number;
  cantidadRegistros: number;
  cantidadEstimada: number;
  errores: unknown;
}

interface RespuestaListaTareas {
  estado: string;
  datos: TareaImportacion[];
}

/** Consulta cruda de `listaTareas` — expuesta aparte para poder tomar una foto "antes" de importar. */
export async function listaTareas(page: Page, libro: Libro): Promise<TareaImportacion[]> {
  const codigo = CODIGO_LIBRO[libro];
  const respuesta = (await page.evaluate(async (c) => {
    const res = await fetch(`ajax.do?f=listaTareas&c=${c}`, { credentials: "include" });
    return res.json();
  }, codigo)) as RespuestaListaTareas;

  if (respuesta.estado !== "ok") {
    throw new Error(`listaTareas respondió estado=${respuesta.estado}`);
  }

  return respuesta.datos;
}

/**
 * Poll de `ajax.do?f=listaTareas&c={21|31}` (mismo endpoint que alimenta el modal "Historial de
 * Importaciones...") hasta que aparezca una tarea NUEVA (su `codigo` no estaba en
 * `codigosPrevios`) y quede en estado "TE" (Terminada).
 *
 * ⚠️ Bug real corregido (25/08/2026, encontrado inspeccionando el historial a mano tras el fix
 * del caso RED COLON): la versión anterior ordenaba las tareas por `codigo` descendente
 * asumiendo que era cronológico — NO lo es. Se confirmó en vivo: una tarea del 24/08 tenía
 * `codigo=130644945` (de una categoría/rango distinto) mientras una tarea real del 25/08 tenía
 * `codigo=77583362` (numéricamente MENOR). Con el sort viejo, el poll devolvía la tarea vieja
 * (ya "TE" de antes) en la primera vuelta, sin esperar nunca a la tarea real de esta corrida —
 * un falso positivo silencioso. `codigosPrevios` (la foto de `listaTareas` tomada ANTES de
 * clickear "Importar") elimina la ambigüedad sin depender de ningún supuesto sobre el `codigo`.
 *
 * Devuelve la tarea final (con el detalle agregados/erróneos ya incluido). Tira error si se
 * agota el timeout sin que aparezca.
 */
export async function esperarImportacion(
  page: Page,
  libro: Libro,
  codigosPrevios: ReadonlySet<number>,
  opts: { timeoutMs?: number; intervaloMs?: number } = {},
): Promise<TareaImportacion> {
  const timeoutMs = opts.timeoutMs ?? 120_000;
  const intervaloMs = opts.intervaloMs ?? 3_000;
  const limite = Date.now() + timeoutMs;

  while (Date.now() < limite) {
    const datos = await listaTareas(page, libro);
    const nueva = datos.find((t) => !codigosPrevios.has(t.codigo) && t.estado === "TE");
    if (nueva) {
      return nueva;
    }

    await new Promise((resolve) => setTimeout(resolve, intervaloMs));
  }

  throw new Error(
    `La importación de ${libro} no terminó dentro de ${timeoutMs / 1000}s (poll de listaTareas).`,
  );
}
