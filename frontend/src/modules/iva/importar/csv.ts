/** Utilidades de parseo de CSV para la importación de comprobantes (sin dependencias). */

export interface CsvParsed {
  delimiter: string
  headers: string[]
  rows: string[][]
}

/** Detecta el separador más probable (`;` o `,` o tab) según la primera línea. */
function detectDelimiter(firstLine: string): string {
  const candidates = [';', ',', '\t']
  let best = ','
  let bestCount = -1
  for (const d of candidates) {
    const count = firstLine.split(d).length - 1
    if (count > bestCount) {
      bestCount = count
      best = d
    }
  }
  return best
}

/** Parser CSV que respeta comillas dobles (RFC 4180-ish) y CRLF/LF. */
export function parseCsv(text: string): CsvParsed {
  // Quita BOM si viene de Excel/Windows.
  const clean = text.replace(/^﻿/, '')
  const firstLineEnd = clean.search(/\r?\n/)
  const firstLine = firstLineEnd === -1 ? clean : clean.slice(0, firstLineEnd)
  const delimiter = detectDelimiter(firstLine)

  const rows: string[][] = []
  let field = ''
  let row: string[] = []
  let inQuotes = false

  for (let i = 0; i < clean.length; i++) {
    const c = clean[i]
    if (inQuotes) {
      if (c === '"') {
        if (clean[i + 1] === '"') {
          field += '"'
          i++
        } else {
          inQuotes = false
        }
      } else {
        field += c
      }
    } else if (c === '"') {
      inQuotes = true
    } else if (c === delimiter) {
      row.push(field)
      field = ''
    } else if (c === '\n') {
      row.push(field)
      rows.push(row)
      field = ''
      row = []
    } else if (c === '\r') {
      // ignorar; el \n siguiente cierra la fila
    } else {
      field += c
    }
  }
  // Última fila sin salto final.
  if (field !== '' || row.length > 0) {
    row.push(field)
    rows.push(row)
  }

  // Descarta filas totalmente vacías.
  const nonEmpty = rows.filter((r) => r.some((v) => v.trim() !== ''))
  const headers = (nonEmpty.shift() ?? []).map((h) => h.trim())
  return { delimiter, headers, rows: nonEmpty }
}

/**
 * Normaliza un número en formato es-AR ("1.234,56") o simple ("1234.56") a string
 * decimal con punto, apto para el backend. Devuelve '' si no hay valor.
 */
export function normNumber(raw: string | undefined): string {
  if (raw == null) return ''
  let s = raw.trim().replace(/\s|\$/g, '')
  if (s === '') return ''
  const hasComma = s.includes(',')
  const hasDot = s.includes('.')
  if (hasComma && hasDot) {
    // "1.234,56" → el punto es miles, la coma decimal.
    s = s.replace(/\./g, '').replace(',', '.')
  } else if (hasComma) {
    // "1234,56" → coma decimal.
    s = s.replace(',', '.')
  }
  // solo dígitos, signo y punto
  s = s.replace(/[^0-9.-]/g, '')
  return s
}

/** Normaliza una fecha dd/mm/yyyy (o dd-mm-yyyy) a Y-m-d. Deja pasar la ya-ISO. */
export function normDate(raw: string | undefined): string {
  if (raw == null) return ''
  const s = raw.trim()
  if (s === '') return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s
  const m = s.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})$/)
  if (m) {
    const [, d, mo, y] = m
    const year = y.length === 2 ? `20${y}` : y
    return `${year}-${mo.padStart(2, '0')}-${d.padStart(2, '0')}`
  }
  return s
}
