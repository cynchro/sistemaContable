import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CCard, CCardHeader, CCardBody, CFormSelect, CNav, CNavItem, CNavLink } from '@coreui/react'
import { listEmpresas } from '../../api/empresas'
import EmpleadosTab from './EmpleadosTab'
import ConceptosTab from './ConceptosTab'
import LiquidacionesTab from './LiquidacionesTab'

type Tab = 'empleados' | 'conceptos' | 'liquidaciones'

export default function SueldosPage() {
  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const [empresaId, setEmpresaId] = useState<number | null>(null)
  const [tab, setTab] = useState<Tab>('empleados')

  useEffect(() => {
    if (empresaId == null && empresas && empresas.length > 0) setEmpresaId(empresas[0].id)
  }, [empresas, empresaId])

  return (
    <>
      <h2 className="mb-4">Sueldos</h2>
      <CCard>
        <CCardHeader className="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <CFormSelect
            size="sm"
            style={{ minWidth: 240 }}
            value={empresaId ?? ''}
            onChange={(e) => setEmpresaId(e.target.value ? Number(e.target.value) : null)}
          >
            <option value="">— Empresa —</option>
            {empresas?.map((em) => (
              <option key={em.id} value={em.id}>
                {em.nombre}
              </option>
            ))}
          </CFormSelect>
          <CNav variant="tabs" className="border-bottom-0">
            {([
              ['empleados', 'Legajos'],
              ['conceptos', 'Conceptos'],
              ['liquidaciones', 'Liquidaciones'],
            ] as [Tab, string][]).map(([k, label]) => (
              <CNavItem key={k}>
                <CNavLink active={tab === k} style={{ cursor: 'pointer' }} onClick={() => setTab(k)}>
                  {label}
                </CNavLink>
              </CNavItem>
            ))}
          </CNav>
        </CCardHeader>
        <CCardBody>
          {empresaId == null ? (
            <div className="text-body-secondary py-4 text-center">Elegí una empresa para gestionar sus sueldos.</div>
          ) : (
            <>
              {tab === 'empleados' && <EmpleadosTab empresaId={empresaId} />}
              {tab === 'conceptos' && <ConceptosTab empresaId={empresaId} />}
              {tab === 'liquidaciones' && <LiquidacionesTab empresaId={empresaId} />}
            </>
          )}
        </CCardBody>
      </CCard>
    </>
  )
}
