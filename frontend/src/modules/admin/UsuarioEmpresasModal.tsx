import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CButton,
  CFormCheck,
  CSpinner,
  CAlert,
} from '@coreui/react'
import { empresasDeUsuario, asignarEmpresas, type Usuario } from '../../api/admin'
import { listEmpresas } from '../../api/empresas'
import { apiError } from './shared'

/**
 * Empresas "asignadas" a un usuario — filtro de visibilidad, no restricción dura
 * (pedido pendiente aparte, ver WhatsApp con el cliente 11/08/2026). Sin ninguna
 * asignación, el usuario sigue viendo todas las empresas del tenant; por eso la
 * lista vacía se muestra como "ve todas", no como un error.
 */
export default function UsuarioEmpresasModal({ usuario, onClose }: { usuario: Usuario; onClose: () => void }) {
  const qc = useQueryClient()
  const empresasQ = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const asignadasQ = useQuery({
    queryKey: ['usuario-empresas', usuario.id],
    queryFn: () => empresasDeUsuario(usuario.id),
  })

  const [seleccion, setSeleccion] = useState<Set<number>>(new Set())
  useEffect(() => {
    if (asignadasQ.data) setSeleccion(new Set(asignadasQ.data.map((e) => e.id)))
  }, [asignadasQ.data])

  const guardar = useMutation({
    mutationFn: () => asignarEmpresas(usuario.id, Array.from(seleccion)),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['usuario-empresas', usuario.id] }),
  })

  const toggle = (id: number) => {
    setSeleccion((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const cargando = empresasQ.isLoading || asignadasQ.isLoading
  const error = empresasQ.error ?? asignadasQ.error

  return (
    <CModal visible onClose={onClose} alignment="center" size="lg">
      <CModalHeader>
        <CModalTitle>Empresas asignadas: {usuario.usuario}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        {cargando && <CSpinner />}
        {error && <CAlert color="danger">{apiError(error, 'No se pudieron cargar las empresas.')}</CAlert>}
        {guardar.isError && (
          <CAlert color="danger">{apiError(guardar.error, 'No se pudo guardar la asignación.')}</CAlert>
        )}
        {!cargando && !error && (
          <>
            <CAlert color="info" className="small">
              Sin ninguna empresa marcada, el usuario sigue viendo todas las empresas del estudio.
              Esto solo filtra qué ve en sus selectores — no restringe permisos.
            </CAlert>
            <div style={{ maxHeight: 360, overflowY: 'auto' }}>
              {empresasQ.data?.map((e) => (
                <CFormCheck
                  key={e.id}
                  id={`empresa-${e.id}`}
                  label={`${e.nombre}${e.cuit ? ` (${e.cuit})` : ''}`}
                  checked={seleccion.has(e.id)}
                  onChange={() => toggle(e.id)}
                />
              ))}
              {empresasQ.data?.length === 0 && (
                <p className="text-body-secondary mb-0">No hay empresas cargadas.</p>
              )}
            </div>
          </>
        )}
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" onClick={onClose}>
          Cerrar
        </CButton>
        <CButton color="primary" onClick={() => guardar.mutate()} disabled={cargando || guardar.isPending}>
          {guardar.isPending ? 'Guardando…' : 'Guardar'}
        </CButton>
      </CModalFooter>
    </CModal>
  )
}
