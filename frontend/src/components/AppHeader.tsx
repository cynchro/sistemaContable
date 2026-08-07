import { useNavigate } from 'react-router-dom'
import {
  CHeader,
  CHeaderToggler,
  CHeaderNav,
  CContainer,
  CDropdown,
  CDropdownToggle,
  CDropdownMenu,
  CDropdownItem,
  CDropdownHeader,
  CDropdownDivider,
  CFormSwitch,
  useColorModes,
} from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { cilMenu, cilSun, cilMoon, cilContrast, cilUser, cilAccountLogout, cilLifeRing } from '@coreui/icons'
import { useUi } from '../layout/UiContext'
import { useAuth } from '../auth/AuthContext'
import { useTourSettings } from '../tours/TourContext'
import ActiveSelector from './ActiveSelector'

export default function AppHeader() {
  const { sidebarShow, setSidebarShow } = useUi()
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const { colorMode, setColorMode } = useColorModes('coreui-theme')
  const { enabled: toursEnabled, setEnabled: setToursEnabled, resetSeen } = useTourSettings()

  const onLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  const themeIcon = colorMode === 'dark' ? cilMoon : colorMode === 'auto' ? cilContrast : cilSun

  return (
    <CHeader position="sticky" className="mb-4 border-bottom">
      <CContainer fluid>
        <CHeaderToggler onClick={() => setSidebarShow(!sidebarShow)} className="px-md-0 me-md-3">
          <CIcon icon={cilMenu} size="lg" />
        </CHeaderToggler>

        <CHeaderNav className="d-none d-md-flex align-items-center">
          <ActiveSelector />
        </CHeaderNav>

        <CHeaderNav className="ms-auto align-items-center">
          <CDropdown variant="nav-item" placement="bottom-end" id="tour-help-toggle">
            <CDropdownToggle caret={false} title="Recorridos guiados">
              <CIcon icon={cilLifeRing} size="lg" />
            </CDropdownToggle>
            <CDropdownMenu style={{ minWidth: 260 }}>
              <CDropdownHeader className="bg-body-secondary fw-semibold">Recorridos guiados</CDropdownHeader>
              <div className="px-3 py-2">
                <CFormSwitch
                  id="tours-enabled-switch"
                  label={toursEnabled ? 'Activados' : 'Desactivados'}
                  checked={toursEnabled}
                  onChange={(e) => setToursEnabled(e.target.checked)}
                />
                <div className="text-body-secondary small mt-1">
                  Muestran un recorrido la primera vez que entrás a cada pantalla. El de Inicio es un mapa del
                  sistema completo; el resto explica los controles de esa pantalla puntual.
                </div>
              </div>
              <CDropdownDivider />
              <CDropdownItem
                role="button"
                onClick={() => {
                  resetSeen()
                  window.location.reload()
                }}
              >
                Reiniciar recorridos vistos
              </CDropdownItem>
            </CDropdownMenu>
          </CDropdown>

          <CDropdown variant="nav-item" placement="bottom-end">
            <CDropdownToggle caret={false}>
              <CIcon icon={themeIcon} size="lg" />
            </CDropdownToggle>
            <CDropdownMenu>
              <CDropdownItem active={colorMode === 'light'} onClick={() => setColorMode('light')} role="button">
                <CIcon className="me-2" icon={cilSun} size="lg" /> Claro
              </CDropdownItem>
              <CDropdownItem active={colorMode === 'dark'} onClick={() => setColorMode('dark')} role="button">
                <CIcon className="me-2" icon={cilMoon} size="lg" /> Oscuro
              </CDropdownItem>
              <CDropdownItem active={colorMode === 'auto'} onClick={() => setColorMode('auto')} role="button">
                <CIcon className="me-2" icon={cilContrast} size="lg" /> Auto
              </CDropdownItem>
            </CDropdownMenu>
          </CDropdown>

          <CDropdown variant="nav-item" placement="bottom-end">
            <CDropdownToggle caret={false}>
              <CIcon icon={cilUser} size="lg" className="me-2" />
              Usuario #{user?.sub}
            </CDropdownToggle>
            <CDropdownMenu>
              <CDropdownHeader className="bg-body-secondary fw-semibold">Sesión</CDropdownHeader>
              <CDropdownItem onClick={onLogout} role="button">
                <CIcon className="me-2" icon={cilAccountLogout} /> Salir
              </CDropdownItem>
            </CDropdownMenu>
          </CDropdown>
        </CHeaderNav>
      </CContainer>
    </CHeader>
  )
}
