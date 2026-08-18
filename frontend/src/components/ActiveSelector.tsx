import { useEffect, useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  CDropdown,
  CDropdownToggle,
  CDropdownMenu,
  CDropdownItem,
  CDropdownHeader,
  CBadge,
  CAlert,
} from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { cilBuilding, cilCalendar } from '@coreui/icons'
import { listEmpresas } from '../api/empresas'
import { listPeriodos } from '../api/periodos'
import { useActive } from '../layout/ActiveContext'
import { pingEmpresa } from '../api/empresaLock'
import { useOcuparEmpresa } from '../hooks/useOcuparEmpresa'

const PING_MS = 60_000

/**
 * Selector de "empresa activa + período activo" en el header. Rehidrata desde los
 * IDs persistidos y, al cambiar de empresa, invalida el período (pertenece a la
 * empresa). Muestra el estado Abierto/Cerrado del período como badge.
 *
 * Al elegir una empresa (o rehidratarla) pide el lock (WhatsApp con el cliente,
 * 11/08/2026): si otro usuario no-admin ya la tiene ocupada, el backend devuelve
 * 409 y la selección se rechaza (bloqueo total); si el usuario es admin entra en
 * modo observador (puede ver, no modificar). Mientras la empresa sigue activa en
 * modo propio, un heartbeat cada 60s mantiene el lock vivo — el `liberarEmpresa`
 * de la empresa anterior vive en `ActiveContext.setEmpresa`, no acá.
 */
export default function ActiveSelector() {
  const { empresa, periodo, setPeriodo, activeEmpresaId, activePeriodoId, lockEstado, lockOcupadoPor } = useActive()
  const { ocupar, error: lockError, reset: clearLockError } = useOcuparEmpresa()
  const rehidratando = useRef<number | null>(null)

  const empresasQ = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const periodosQ = useQuery({
    queryKey: ['periodos', empresa?.id],
    queryFn: () => listPeriodos(empresa!.id),
    enabled: !!empresa,
  })

  // Rehidratar la empresa activa desde el id persistido cuando llega el listado.
  useEffect(() => {
    if (!empresa && activeEmpresaId && empresasQ.data && rehidratando.current !== activeEmpresaId) {
      const found = empresasQ.data.find((e) => e.id === activeEmpresaId)
      if (found) {
        rehidratando.current = activeEmpresaId
        void ocupar(found)
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [empresa, activeEmpresaId, empresasQ.data])

  // Rehidratar el período activo desde el id persistido.
  useEffect(() => {
    if (!periodo && activePeriodoId && periodosQ.data) {
      const found = periodosQ.data.find((p) => p.id === activePeriodoId)
      if (found) setPeriodo(found)
    }
  }, [periodo, activePeriodoId, periodosQ.data, setPeriodo])

  // Heartbeat: mientras la empresa activa está en modo propio, renueva el lock.
  useEffect(() => {
    if (!empresa || lockEstado !== 'propio') return
    const id = setInterval(() => {
      pingEmpresa(empresa.id).catch(() => {
        // Si el ping falla el lock expira solo por timeout del backend (5 min).
      })
    }, PING_MS)
    return () => clearInterval(id)
  }, [empresa, lockEstado])

  const pickEmpresa = (e: NonNullable<typeof empresa>) => {
    setPeriodo(null) // cambiar de empresa invalida el período activo
    void ocupar(e)
  }

  return (
    <>
      {lockError && (
        <CAlert
          color="danger"
          dismissible
          onClose={clearLockError}
          className="position-fixed top-0 start-50 translate-middle-x mt-2"
          style={{ zIndex: 2000 }}
        >
          {lockError}
        </CAlert>
      )}
      {lockEstado === 'observador' && (
        <CAlert
          color="warning"
          dismissible
          className="position-fixed top-0 start-50 translate-middle-x mt-2"
          style={{ zIndex: 2000 }}
        >
          Modo observador: {lockOcupadoPor} está trabajando en esta empresa. Podés ver, pero no
          modificar.
        </CAlert>
      )}
      <CDropdown variant="nav-item" placement="bottom-start" id="tour-empresa-selector">
        <CDropdownToggle caret={false} className="text-body">
          <CIcon icon={cilBuilding} className="me-2" />
          <span className="d-none d-md-inline text-body-secondary">Empresa:&nbsp;</span>
          <strong>{empresa?.nombre ?? '— elegir —'}</strong>
        </CDropdownToggle>
        <CDropdownMenu style={{ maxHeight: 360, overflowY: 'auto' }}>
          <CDropdownHeader className="bg-body-secondary fw-semibold">Empresa activa</CDropdownHeader>
          {empresasQ.isLoading && <CDropdownItem disabled>Cargando…</CDropdownItem>}
          {empresasQ.data?.length === 0 && <CDropdownItem disabled>Sin empresas</CDropdownItem>}
          {empresasQ.data?.map((e) => (
            <CDropdownItem
              key={e.id}
              active={e.id === empresa?.id}
              onClick={() => pickEmpresa(e)}
              role="button"
            >
              {e.nombre}
            </CDropdownItem>
          ))}
        </CDropdownMenu>
      </CDropdown>

      <CDropdown variant="nav-item" placement="bottom-start" id="tour-periodo-selector">
        <CDropdownToggle caret={false} disabled={!empresa} className="text-body">
          <CIcon icon={cilCalendar} className="me-2" />
          <span className="d-none d-md-inline text-body-secondary">Período:&nbsp;</span>
          <strong>{periodo?.nombre ?? '— elegir —'}</strong>
          {periodo && (
            <CBadge color={periodo.cerrado === 'S' ? 'secondary' : 'success'} className="ms-2">
              {periodo.cerrado === 'S' ? 'Cerrado' : 'Abierto'}
            </CBadge>
          )}
        </CDropdownToggle>
        <CDropdownMenu style={{ maxHeight: 360, overflowY: 'auto' }}>
          <CDropdownHeader className="bg-body-secondary fw-semibold">Período activo</CDropdownHeader>
          {!empresa && <CDropdownItem disabled>Elegí una empresa primero</CDropdownItem>}
          {empresa && periodosQ.isLoading && <CDropdownItem disabled>Cargando…</CDropdownItem>}
          {empresa && periodosQ.data?.length === 0 && (
            <CDropdownItem disabled>Sin períodos</CDropdownItem>
          )}
          {periodosQ.data?.map((p) => (
            <CDropdownItem
              key={p.id}
              active={p.id === periodo?.id}
              onClick={() => setPeriodo(p)}
              role="button"
            >
              {p.nombre}
              <CBadge
                color={p.cerrado === 'S' ? 'secondary' : 'success'}
                className="ms-2"
              >
                {p.cerrado === 'S' ? 'Cerrado' : 'Abierto'}
              </CBadge>
            </CDropdownItem>
          ))}
        </CDropdownMenu>
      </CDropdown>
    </>
  )
}
