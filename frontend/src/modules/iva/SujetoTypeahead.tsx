import { useRef, useState } from 'react'
import { CFormInput } from '@coreui/react'
import type { Sujeto } from '../../api/sujetos'

interface Props {
  sujetos: Sujeto[] | undefined
  /** Texto actual (nombre/razón social del comprobante). */
  value: string
  /** El usuario tipeó (queda como sujeto ocasional hasta elegir uno de la lista). */
  onText: (text: string) => void
  /** Eligió un sujeto del padrón del estudio (autocompleta datos fiscales). */
  onPick: (s: Sujeto) => void
  placeholder?: string
  id?: string
}

/** Búsqueda inteligente de cliente/proveedor (réplica del Visual IVA): tipeás nombre o
 * CUIT y se despliega la lista de coincidencias; al elegir uno se autocompletan sus datos.
 * Filtra sobre el listado ya cargado (sin llamadas extra). */
export default function SujetoTypeahead({ sujetos, value, onText, onPick, placeholder, id }: Props) {
  const [open, setOpen] = useState(false)
  const blurTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const q = value.trim().toLowerCase()
  const qDigits = q.replace(/\D/g, '')
  const matches =
    q.length < 2
      ? []
      : (sujetos ?? [])
          .filter(
            (s) =>
              s.nombre.toLowerCase().includes(q) ||
              (qDigits.length >= 2 && (s.cuit ?? '').replace(/\D/g, '').includes(qDigits)),
          )
          .slice(0, 8)

  const pick = (s: Sujeto) => {
    onPick(s)
    setOpen(false)
  }

  return (
    <div className="position-relative">
      <CFormInput
        id={id}
        autoComplete="off"
        placeholder={placeholder}
        value={value}
        onChange={(e) => {
          onText(e.target.value)
          setOpen(true)
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => {
          blurTimer.current = setTimeout(() => setOpen(false), 150)
        }}
      />
      {open && matches.length > 0 && (
        <div
          className="position-absolute w-100 bg-body border rounded shadow-sm"
          style={{ zIndex: 1060, maxHeight: 220, overflowY: 'auto', top: '100%' }}
          onMouseDown={() => clearTimeout(blurTimer.current)}
        >
          {matches.map((s) => (
            <button
              key={s.id}
              type="button"
              className="dropdown-item text-body px-3 py-1 text-start w-100 border-0 bg-transparent"
              onClick={() => pick(s)}
            >
              {s.nombre}
              {s.cuit ? <span className="text-body-secondary small"> · {s.cuit}</span> : null}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
