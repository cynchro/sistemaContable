import { CFooter } from '@coreui/react'

/** Repo en GitHub: la versión del footer enlaza al tag/release correspondiente para cotejar. */
const REPO = 'https://github.com/cynchro/sistemaContable'

export default function AppFooter() {
  return (
    <CFooter className="px-4">
      <div>Sistema Contable · Estudio Haddad</div>
      <a
        className="ms-auto text-body-secondary text-decoration-none"
        href={`${REPO}/releases/tag/v${__APP_VERSION__}`}
        target="_blank"
        rel="noreferrer"
        title="Ver esta versión en GitHub (tag)"
      >
        v{__APP_VERSION__}
      </a>
    </CFooter>
  )
}
