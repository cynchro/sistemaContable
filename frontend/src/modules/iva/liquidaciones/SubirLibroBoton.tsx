import { useQuery } from '@tanstack/react-query'
import { CAlert, CButton } from '@coreui/react'
import { listEmpresas } from '../../../api/empresas'
import { listCredenciales } from '../../../api/credenciales'
import { LABEL_LIBRO, LiquidacionEstado, useLiquidacionProceso } from './LiquidacionProceso'

/**
 * Botón "Procesar" (subir) para las pantallas de Compras/Ventas: arma y sube a ARCA (borrador) el
 * libro tal como está cargado en ecosistema para este período — nunca presenta la declaración
 * jurada. El "traer" (ARCA → ecosistema) sigue viviendo solo en la pestaña "Liquidar IVA" del
 * Libro IVA; acá el libro y la dirección van fijos, no hay selectores.
 */
export function SubirLibroBoton({ eId, pId, libro }: { eId: number; pId: number; libro: 'compras' | 'ventas' }) {
  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const empresa = empresas?.find((e) => e.id === eId)

  const { data: credenciales } = useQuery({
    queryKey: ['credenciales', eId],
    queryFn: () => listCredenciales(eId),
  })
  const credencialFiscal = credenciales?.find((c) => c.tipo === 'fiscal' && c.sistema === 'AFIP')

  const { enCursoId, setEnCursoId, actual, crear, enCurso, error, modalVisible, setModalVisible } =
    useLiquidacionProceso(eId, pId)

  const sinCuit = Boolean(empresa) && !empresa?.cuit
  const sinCredencial = !credencialFiscal
  const puedeProcesar = !sinCuit && !sinCredencial && !enCurso

  return (
    <div id="tour-subir-libro" className="mt-4 pt-3 border-top">
      {error && <CAlert color="danger">{error}</CAlert>}
      {(sinCuit || sinCredencial) && (
        <CAlert color="warning" className="small py-2">
          Falta {sinCuit ? 'el CUIT de la empresa' : 'la Clave Fiscal de ARCA'} — cargalo desde la pestaña
          "Liquidar IVA" del Libro IVA antes de subir comprobantes.
        </CAlert>
      )}
      <div className="d-flex align-items-center gap-2 flex-wrap">
        <CButton
          color="primary"
          size="sm"
          disabled={!puedeProcesar || crear.isPending}
          onClick={() => crear.mutate({ direccion: 'subir', libro })}
        >
          Procesar
        </CButton>
        <span className="text-body-secondary small">
          Sube a ARCA (borrador) los comprobantes de {LABEL_LIBRO[libro]} de este período tal como están
          cargados acá. No presenta la declaración jurada.
        </span>
      </div>
      <div className="mt-2">
        <LiquidacionEstado
          enCursoId={enCursoId}
          actual={actual}
          enCurso={enCurso}
          modalVisible={modalVisible}
          setModalVisible={setModalVisible}
          setEnCursoId={setEnCursoId}
        />
      </div>
    </div>
  )
}
