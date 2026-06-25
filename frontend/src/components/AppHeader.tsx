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
  useColorModes,
} from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { cilMenu, cilSun, cilMoon, cilContrast, cilUser, cilAccountLogout } from '@coreui/icons'
import { useUi } from '../layout/UiContext'
import { useAuth } from '../auth/AuthContext'

export default function AppHeader() {
  const { sidebarShow, setSidebarShow } = useUi()
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const { colorMode, setColorMode } = useColorModes('coreui-theme')

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

        <CHeaderNav className="ms-auto align-items-center">
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
