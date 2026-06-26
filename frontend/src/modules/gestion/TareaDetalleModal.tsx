import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CButton,
  CFormTextarea,
  CSpinner,
  CListGroup,
  CListGroupItem,
} from '@coreui/react'
import { getComentarios, addComentario, getHistorial, type Tarea } from '../../api/gestion'
import { humaniza } from './shared'

export default function TareaDetalleModal({ tarea, onClose }: { tarea: Tarea; onClose: () => void }) {
  const qc = useQueryClient()
  const [texto, setTexto] = useState('')

  const comentarios = useQuery({ queryKey: ['comentarios', tarea.id], queryFn: () => getComentarios(tarea.id) })
  const historial = useQuery({ queryKey: ['historial', tarea.id], queryFn: () => getHistorial(tarea.id) })

  const addM = useMutation({
    mutationFn: () => addComentario(tarea.id, texto.trim()),
    onSuccess: () => {
      setTexto('')
      qc.invalidateQueries({ queryKey: ['comentarios', tarea.id] })
    },
  })

  return (
    <CModal visible onClose={onClose} alignment="center" size="lg">
      <CModalHeader>
        <CModalTitle>{tarea.titulo}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <h6>Comentarios</h6>
        {comentarios.isLoading && <CSpinner size="sm" />}
        <CListGroup className="mb-2">
          {comentarios.data?.map((c) => (
            <CListGroupItem key={c.id}>
              <div>{c.comentario}</div>
              <div className="text-body-secondary small">{c.created_at}</div>
            </CListGroupItem>
          ))}
          {comentarios.data?.length === 0 && (
            <CListGroupItem className="text-body-secondary">Sin comentarios.</CListGroupItem>
          )}
        </CListGroup>
        <div className="d-flex gap-2 mb-4">
          <CFormTextarea rows={1} value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Agregar comentario…" />
          <CButton color="primary" disabled={!texto.trim() || addM.isPending} onClick={() => addM.mutate()}>
            Enviar
          </CButton>
        </div>

        <h6>Historial de estados</h6>
        {historial.isLoading && <CSpinner size="sm" />}
        <CListGroup>
          {historial.data?.map((h) => (
            <CListGroupItem key={h.id} className="d-flex justify-content-between">
              <span>
                {humaniza(h.estado_anterior)} → <strong>{humaniza(h.estado_nuevo)}</strong>
                {h.observacion ? ` — ${h.observacion}` : ''}
              </span>
              <span className="text-body-secondary small">{h.created_at}</span>
            </CListGroupItem>
          ))}
          {historial.data?.length === 0 && (
            <CListGroupItem className="text-body-secondary">Sin cambios de estado.</CListGroupItem>
          )}
        </CListGroup>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" onClick={onClose}>
          Cerrar
        </CButton>
      </CModalFooter>
    </CModal>
  )
}
