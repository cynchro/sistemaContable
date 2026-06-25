import { CCard, CCardBody } from '@coreui/react'

/** Placeholder para módulos cuyo frontend todavía no se construyó (el backend ya existe). */
export default function EnConstruccion({ titulo }: { titulo: string }) {
  return (
    <CCard>
      <CCardBody className="text-center text-body-secondary py-5">
        <h4 className="mb-2">{titulo}</h4>
        <p className="mb-0">Pantalla en construcción. El backend de este módulo ya está disponible vía API.</p>
      </CCardBody>
    </CCard>
  )
}
