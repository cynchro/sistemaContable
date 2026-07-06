import { useQuery } from '@tanstack/react-query'
import { CAlert, CBadge } from '@coreui/react'
import { getAfipAmbiente } from '../api/afip'

/**
 * Aviso del ambiente AFIP activo. En homologación los comprobantes son de PRUEBA
 * (sin validez fiscal): conviene dejarlo bien visible para no confundir un CAE de
 * prueba con uno real. `variant="badge"` para un chip inline (listados).
 */
export default function AfipAmbienteBanner({ variant = 'banner' }: { variant?: 'banner' | 'badge' }) {
  const { data } = useQuery({ queryKey: ['afip-ambiente'], queryFn: getAfipAmbiente })
  if (!data) return null
  const homo = data.env !== 'produccion'

  if (variant === 'badge') {
    return (
      <CBadge
        color={homo ? 'warning' : 'success'}
        title={homo ? 'AFIP en homologación: comprobantes de prueba, sin validez fiscal' : 'AFIP en producción'}
      >
        AFIP: {homo ? 'HOMOLOGACIÓN' : 'PRODUCCIÓN'}
      </CBadge>
    )
  }

  return homo ? (
    <CAlert color="warning">
      <strong>AFIP: HOMOLOGACIÓN</strong> — los comprobantes que emitas son de <strong>prueba</strong> y{' '}
      <strong>no tienen validez fiscal</strong>.{data.cuit ? ` CUIT de prueba: ${data.cuit}.` : ''}
    </CAlert>
  ) : (
    <CAlert color="success" className="py-2">
      <strong>AFIP: PRODUCCIÓN</strong> — emisión real de comprobantes.
    </CAlert>
  )
}
