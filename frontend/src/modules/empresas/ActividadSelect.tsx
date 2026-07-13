import { useMemo, useRef, useState } from 'react'
import { CFormInput } from '@coreui/react'
import type { CatalogoItem } from '../../api/catalogos'

interface Props {
  actividades: CatalogoItem[] | undefined
  /** id de la actividad seleccionada (string, '' = ninguna). */
  value: string
  onChange: (id: string) => void
  placeholder?: string
  id?: string
}

/** Selector con búsqueda sobre el catálogo de actividades (AFIP/NAES), réplica del
 * desplegable del Visual IVA: se tipea código o descripción y se elige de la lista.
 * Filtra sobre el catálogo ya cargado (sin llamadas extra). */
export default function ActividadSelect({ actividades, value, onChange, placeholder, id }: Props) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState<string | null>(null)
  const blurTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const byId = useMemo(() => {
    const m = new Map<string, CatalogoItem>()
    for (const a of actividades ?? []) m.set(String(a.id), a)
    return m
  }, [actividades])

  const label = (a: CatalogoItem) => `${a.codigo ? a.codigo + ' — ' : ''}${a.nombre}`

  // Texto mostrado: mientras se busca, lo tipeado; si no, la actividad elegida.
  const selected = value ? byId.get(value) : undefined
  const text = query ?? (selected ? label(selected) : '')

  const q = (query ?? '').trim().toLowerCase()
  const matches = useMemo(() => {
    if (q.length < 2) return []
    return (actividades ?? [])
      .filter((a) => a.nombre.toLowerCase().includes(q) || (a.codigo ?? '').toLowerCase().includes(q))
      .slice(0, 20)
  }, [actividades, q])

  const pick = (a: CatalogoItem | null) => {
    onChange(a ? String(a.id) : '')
    setQuery(null)
    setOpen(false)
  }

  return (
    <div className="position-relative">
      <CFormInput
        id={id}
        autoComplete="off"
        placeholder={placeholder ?? 'Buscar por código o descripción…'}
        value={text}
        onChange={(e) => {
          setQuery(e.target.value)
          setOpen(true)
        }}
        onFocus={() => {
          setQuery('')
          setOpen(true)
        }}
        onBlur={() => {
          blurTimer.current = setTimeout(() => {
            setQuery(null)
            setOpen(false)
          }, 150)
        }}
      />
      {open && (query ?? '').length >= 2 && (
        <div
          className="position-absolute w-100 bg-body border rounded shadow-sm"
          style={{ zIndex: 1060, maxHeight: 240, overflowY: 'auto', top: '100%' }}
          onMouseDown={() => clearTimeout(blurTimer.current)}
        >
          {value && (
            <button
              type="button"
              className="dropdown-item text-body-secondary px-3 py-1 text-start w-100 border-0 bg-transparent"
              onClick={() => pick(null)}
            >
              — Quitar selección —
            </button>
          )}
          {matches.map((a) => (
            <button
              key={a.id}
              type="button"
              className="dropdown-item text-body px-3 py-1 text-start w-100 border-0 bg-transparent"
              onClick={() => pick(a)}
            >
              {a.codigo ? <span className="text-body-secondary small">{a.codigo} · </span> : null}
              {a.nombre}
            </button>
          ))}
          {matches.length === 0 && (
            <div className="px-3 py-1 text-body-secondary small">Sin coincidencias</div>
          )}
        </div>
      )}
    </div>
  )
}
