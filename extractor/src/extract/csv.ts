/**
 * Parser de CSV chico y sin dependencias, hecho a medida del export de ARCA
 * (Portal IVA → Libro Ventas/Compras → botón CSV): separador `;`, campos
 * entre comillas dobles (con `""` como escape), decimal con coma, sin
 * separador de miles en los ejemplos vistos pero tolerante si apareciera.
 * Encoding ISO-8859-1 — se decodifica ANTES de llegar acá (ver libroCsv.ts,
 * `buffer.toString("latin1")` alcanza, Node lo soporta nativo).
 */

function parseLinea(linea: string, delimitador: string): string[] {
  const campos: string[] = [];
  let actual = "";
  let entreComillas = false;

  for (let i = 0; i < linea.length; i++) {
    const c = linea[i];

    if (entreComillas) {
      if (c === '"') {
        if (linea[i + 1] === '"') {
          actual += '"';
          i++;
        } else {
          entreComillas = false;
        }
      } else {
        actual += c;
      }
      continue;
    }

    if (c === '"') {
      entreComillas = true;
    } else if (c === delimitador) {
      campos.push(actual);
      actual = "";
    } else {
      actual += c;
    }
  }
  campos.push(actual);
  return campos;
}

/** Parsea un CSV completo a filas de objetos, usando la primera línea como encabezados. */
export function parseCsv(texto: string, delimitador = ";"): Record<string, string>[] {
  // Por si el archivo trae BOM (algunos exports UTF-8 lo agregan para que
  // Excel detecte el encoding solo) — si no se saca, corrompe el primer
  // header ("﻿Fecha de Emisión" en vez de "Fecha de Emisión") y ese
  // campo queda inaccesible por nombre. Inofensivo si no está.
  const sinBom = texto.charCodeAt(0) === 0xfeff ? texto.slice(1) : texto;
  const lineas = sinBom.split(/\r\n|\n/).filter((l) => l.trim() !== "");
  if (lineas.length === 0) return [];

  const encabezados = parseLinea(lineas[0], delimitador);
  return lineas.slice(1).map((linea) => {
    const campos = parseLinea(linea, delimitador);
    const fila: Record<string, string> = {};
    encabezados.forEach((h, i) => {
      fila[h] = campos[i] ?? "";
    });
    return fila;
  });
}

/** Convierte un número en formato es-AR ("1.234,56" o "320045,00") a number de JS. */
export function aNumeroArg(valor: string | undefined): number {
  if (valor === undefined || valor.trim() === "") return 0;
  const normalizado = valor.trim().replace(/\./g, "").replace(",", ".");
  const n = Number(normalizado);
  return Number.isFinite(n) ? n : 0;
}

/** Convierte una fecha "YYYY-MM-DD" (formato que ya usa este export) a sí misma, validando. */
export function normalizarFecha(valor: string | undefined): string {
  const v = (valor ?? "").trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) {
    throw new Error(`Fecha inesperada en el CSV de ARCA: "${valor}" (se esperaba YYYY-MM-DD)`);
  }
  return v;
}
