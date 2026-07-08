import { CFooter } from '@coreui/react'

export default function AppFooter() {
  return (
    <CFooter className="px-4">
      <div>Sistema Contable · Estudio Haddad</div>
      <div className="ms-auto text-body-secondary" title={`Compilado: ${__BUILD_DATE__} UTC`}>
        v{__APP_VERSION__} · build {__BUILD_DATE__}
      </div>
    </CFooter>
  )
}
