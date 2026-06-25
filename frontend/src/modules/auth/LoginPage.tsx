import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  CContainer,
  CRow,
  CCol,
  CCard,
  CCardBody,
  CForm,
  CFormInput,
  CFormLabel,
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

export default function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
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
    <CContainer className="min-vh-100 d-flex align-items-center justify-content-center bg-body-tertiary">
      <CRow className="justify-content-center w-100">
        <CCol md={5} lg={4}>
          <CCard className="shadow-sm">
            <CCardBody className="p-4">
              <h3 className="mb-1">Sistema Contable</h3>
              <p className="text-body-secondary mb-4">Ingresá con tu cuenta</p>

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
                  <CFormInput
                    id="clave"
                    type="password"
                    autoComplete="current-password"
                    invalid={!!errors.clave}
                    {...register('clave')}
                  />
                  {errors.clave && <div className="text-danger small mt-1">{errors.clave.message}</div>}
                </div>

                <CButton type="submit" color="primary" className="w-100" disabled={isSubmitting}>
                  {isSubmitting ? <CSpinner size="sm" /> : 'Ingresar'}
                </CButton>
              </CForm>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>
    </CContainer>
  )
}
