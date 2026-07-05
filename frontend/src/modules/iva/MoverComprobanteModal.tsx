import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CFormSelect,
  CFormLabel,
  CButton,
  CBadge,
  CSpinner,
  CAlert,
} from '@coreui/react'
import { listPeriodos } from '../../api/periodos'

interface Props {
  visible: boolean
  empresaId: number
  periodoActualId: number
  /** Descripción del comprobante a mover (para el encabezado). */
  detalle?: string
  moving: boolean
  errorMsg?: string | null
  onClose: () => void
  onMove: (periodoDestinoId: number) => void
}

/** Mueve un comprobante de venta/compra a otro período de la misma empresa.
 * Réplica del "Mover" del Visual IVA. Lista los períodos de la empresa excluyendo
 * el actual; el backend valida que el destino esté abierto. */
export default function MoverComprobanteModal({
  visible,
  empresaId,
  periodoActualId,
  detalle,
  moving,
  errorMsg,
  onClose,
  onMove,
}: Props) {
  const { data: periodos, isLoading } = useQuery({
    queryKey: ['periodos', empresaId],
    queryFn: () => listPeriodos(empresaId),
    enabled: visible,
  })

  const destinos = (periodos ?? []).filter((p) => p.id !== periodoActualId)
  const [destino, setDestino] = useState('')

  useEffect(() => {
    if (visible) setDestino('')
  }, [visible])

  return (
    <CModal visible={visible} onClose={onClose} alignment="center">
      <CModalHeader>
        <CModalTitle>Mover comprobante</CModalTitle>
      </CModalHeader>
      <CModalBody>
        {errorMsg && <CAlert color="danger">{errorMsg}</CAlert>}
        {detalle && <p className="mb-3">Mover {detalle} a otro período.</p>}
        {isLoading && <CSpinner />}
        {!isLoading && destinos.length === 0 && (
          <CAlert color="warning" className="mb-0">
            No hay otros períodos disponibles en esta empresa.
          </CAlert>
        )}
        {destinos.length > 0 && (
          <>
            <CFormLabel htmlFor="periodo-destino">Período destino</CFormLabel>
            <CFormSelect
              id="periodo-destino"
              value={destino}
              onChange={(e) => setDestino(e.target.value)}
            >
              <option value="">— Elegí un período —</option>
              {destinos.map((p) => (
                <option key={p.id} value={p.id} disabled={p.cerrado === 'S'}>
                  {p.nombre} {p.cerrado === 'S' ? '(cerrado)' : ''}
                </option>
              ))}
            </CFormSelect>
            <div className="text-body-secondary small mt-2">
              Los períodos <CBadge color="secondary">cerrados</CBadge> no admiten comprobantes.
            </div>
          </>
        )}
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" onClick={onClose}>
          Cancelar
        </CButton>
        <CButton
          color="primary"
          disabled={moving || !destino}
          onClick={() => destino && onMove(Number(destino))}
        >
          {moving ? 'Moviendo…' : 'Mover'}
        </CButton>
      </CModalFooter>
    </CModal>
  )
}
