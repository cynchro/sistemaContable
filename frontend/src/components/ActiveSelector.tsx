import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  CDropdown,
  CDropdownToggle,
  CDropdownMenu,
  CDropdownItem,
  CDropdownHeader,
  CBadge,
} from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { cilBuilding, cilCalendar } from '@coreui/icons'
import { listEmpresas } from '../api/empresas'
import { listPeriodos } from '../api/periodos'
import { useActive } from '../layout/ActiveContext'

/**
 * Selector de "empresa activa + período activo" en el header. Rehidrata desde los
 * IDs persistidos y, al cambiar de empresa, invalida el período (pertenece a la
 * empresa). Muestra el estado Abierto/Cerrado del período como badge.
 */
export default function ActiveSelector() {
  const { empresa, periodo, setEmpresa, setPeriodo, activeEmpresaId, activePeriodoId } = useActive()

  const empresasQ = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const periodosQ = useQuery({
    queryKey: ['periodos', empresa?.id],
    queryFn: () => listPeriodos(empresa!.id),
    enabled: !!empresa,
  })

  // Rehidratar la empresa activa desde el id persistido cuando llega el listado.
  useEffect(() => {
    if (!empresa && activeEmpresaId && empresasQ.data) {
      const found = empresasQ.data.find((e) => e.id === activeEmpresaId)
      if (found) setEmpresa(found)
    }
  }, [empresa, activeEmpresaId, empresasQ.data, setEmpresa])

  // Rehidratar el período activo desde el id persistido.
  useEffect(() => {
    if (!periodo && activePeriodoId && periodosQ.data) {
      const found = periodosQ.data.find((p) => p.id === activePeriodoId)
      if (found) setPeriodo(found)
    }
  }, [periodo, activePeriodoId, periodosQ.data, setPeriodo])

  const pickEmpresa = (e: NonNullable<typeof empresa>) => {
    setEmpresa(e)
    setPeriodo(null) // cambiar de empresa invalida el período activo
  }

  return (
    <>
      <CDropdown variant="nav-item" placement="bottom-start">
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

      <CDropdown variant="nav-item" placement="bottom-start">
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
