/**
 * Persistencia de los recorridos guiados (Driver.js) en localStorage: si el usuario los
 * activó/desactivó, y qué recorridos ya vio (para no repetirlos solos en cada visita).
 */
const ENABLED_KEY = 'iva_tours_enabled'
const SEEN_KEY = 'iva_tours_seen'

export function getToursEnabled(): boolean {
  const v = localStorage.getItem(ENABLED_KEY)
  return v === null ? true : v === '1'
}

export function setToursEnabled(v: boolean): void {
  localStorage.setItem(ENABLED_KEY, v ? '1' : '0')
}

function readSeen(): Set<string> {
  try {
    const raw = localStorage.getItem(SEEN_KEY)
    return new Set(raw ? (JSON.parse(raw) as string[]) : [])
  } catch {
    return new Set()
  }
}

export function isTourSeen(id: string): boolean {
  return readSeen().has(id)
}

export function markTourSeen(id: string): void {
  const seen = readSeen()
  seen.add(id)
  localStorage.setItem(SEEN_KEY, JSON.stringify([...seen]))
}

export function resetSeenTours(): void {
  localStorage.removeItem(SEEN_KEY)
}
