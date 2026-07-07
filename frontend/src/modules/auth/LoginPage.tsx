import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  CCard,
  CCardBody,
  CForm,
  CFormInput,
  CFormLabel,
  CInputGroup,
  CButton,
  CAlert,
  CSpinner,
} from '@coreui/react'
import { useAuth } from '../../auth/AuthContext'

const schema = z.object({
  usuario: z.string().email('Ingresá un email válido'),
  clave: z.string().min(6, 'Mínimo 6 caracteres'),
})
type FormValues = z.infer<typeof schema>

/** Ojo abierto / tachado para el toggle de "ver contraseña" (SVG inline: sin dep de iconos). */
function EyeIcon({ off }: { off: boolean }) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
      stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
      <circle cx="12" cy="12" r="3" />
      {off && <line x1="3" y1="3" x2="21" y2="21" />}
    </svg>
  )
}

export default function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [showPass, setShowPass] = useState(false)
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const onSubmit = async (values: FormValues) => {
    setError(null)
    try {
      await login(values.usuario, values.clave)
      navigate('/', { replace: true })
    } catch {
      setError('Usuario o contraseña incorrectos.')
    }
  }

  return (
    <div className="login-hero min-vh-100 d-flex align-items-center justify-content-center p-3">
      <CCard className="login-card shadow-lg border-0">
        <CCardBody className="p-4 p-md-5">
          <h3 className="text-center mb-1">Sistema Contable</h3>
          <p className="text-body-secondary text-center mb-4">Ingresá con tu cuenta</p>

          {error && <CAlert color="danger">{error}</CAlert>}

          <CForm onSubmit={handleSubmit(onSubmit)} noValidate>
            <div className="mb-3">
              <CFormLabel htmlFor="usuario">Email</CFormLabel>
              <CFormInput
                id="usuario"
                type="email"
                autoComplete="username"
                invalid={!!errors.usuario}
                {...register('usuario')}
              />
              {errors.usuario && <div className="text-danger small mt-1">{errors.usuario.message}</div>}
            </div>

            <div className="mb-4">
              <CFormLabel htmlFor="clave">Contraseña</CFormLabel>
              <CInputGroup>
                <CFormInput
                  id="clave"
                  type={showPass ? 'text' : 'password'}
                  autoComplete="current-password"
                  invalid={!!errors.clave}
                  {...register('clave')}
                />
                <CButton
                  type="button"
                  color="secondary"
                  variant="outline"
                  className="pw-toggle"
                  tabIndex={-1}
                  aria-label={showPass ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                  title={showPass ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                  onClick={() => setShowPass((v) => !v)}
                >
                  <EyeIcon off={showPass} />
                </CButton>
              </CInputGroup>
              {errors.clave && <div className="text-danger small mt-1">{errors.clave.message}</div>}
            </div>

            <CButton type="submit" color="primary" className="w-100" disabled={isSubmitting}>
              {isSubmitting ? <CSpinner size="sm" /> : 'Ingresar'}
            </CButton>
          </CForm>
        </CCardBody>
      </CCard>
    </div>
  )
}
