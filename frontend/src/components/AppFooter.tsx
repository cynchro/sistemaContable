import { CFooter } from '@coreui/react'

export default function AppFooter() {
  return (
    <CFooter className="px-4">
      <div>Sistema Contable · Estudio Haddad</div>
      <div className="ms-auto text-body-secondary">v{__APP_VERSION__}</div>
    </CFooter>
  )
}
