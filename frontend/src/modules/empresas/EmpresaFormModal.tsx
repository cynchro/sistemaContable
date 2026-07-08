import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useMutation } from '@tanstack/react-query'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CForm,
  CFormInput,
  CFormLabel,
  CButton,
  CSpinner,
} from '@coreui/react'
import type { Empresa, EmpresaInput } from '../../api/empresas'
import { sugerenciaPadron } from '../../api/afip'

const schema = z.object({
  nombre: z.string().min(1, 'El nombre es obligatorio'),
  cuit: z.string().optional(),
  email: z.string().email('Email inválido').optional().or(z.literal('')),
  domicilio: z.string().optional(),
  localidad: z.string().optional(),
  telefono: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  empresa: Empresa | null
  saving: boolean
  onClose: () => void
  onSubmit: (values: EmpresaInput) => void
}

export default function EmpresaFormModal({ visible, empresa, saving, onClose, onSubmit }: Props) {
  const {
    register,
    handleSubmit,
    reset,
    setValue,
    getValues,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const [padronError, setPadronError] = useState<string | null>(null)
  const padron = useMutation({
    mutationFn: (cuit: string) => sugerenciaPadron(cuit),
    onSuccess: (s) => {
      setPadronError(null)
      if (s.nombre) setValue('nombre', s.nombre)
      if (s.domicilio) setValue('domicilio', s.domicilio)
      if (s.localidad) setValue('localidad', s.localidad)
    },
    onError: (e) => {
      const err = e as { response?: { data?: { message?: string } } }
      setPadronError(err.response?.data?.message ?? 'No se pudo consultar el padrón (¿certificado de ARCA?).')
    },
  })

  const buscarEnPadron = () => {
    const cuit = (getValues('cuit') ?? '').replace(/\D/g, '')
    if (cuit.length === 11) padron.mutate(cuit)
    else setPadronError('Ingresá un CUIT de 11 dígitos para buscar en ARCA.')
  }

  useEffect(() => {
    if (visible) {
      setPadronError(null)
      reset({
        nombre: empresa?.nombre ?? '',
        cuit: empresa?.cuit ?? '',
        email: empresa?.email ?? '',
        domicilio: empresa?.domicilio ?? '',
        localidad: empresa?.localidad ?? '',
        telefono: empresa?.telefono ?? '',
      })
    }
  }, [visible, empresa, reset])

  return (
    <CModal visible={visible} onClose={onClose} alignment="center">
      <CModalHeader>
        <CModalTitle>{empresa ? 'Editar empresa' : 'Nueva empresa'}</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(onSubmit)} noValidate>
        <CModalBody>
          <div className="mb-3">
            <CFormLabel htmlFor="cuit">CUIT</CFormLabel>
            <div className="d-flex gap-2">
              <CFormInput
                id="cuit"
                placeholder="Ej. 30-12345678-9"
                {...register('cuit')}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    buscarEnPadron()
                  }
                }}
              />
              <CButton
                type="button"
                color="info"
                disabled={padron.isPending}
                onClick={buscarEnPadron}
                title="Traer los datos de ARCA (padrón) y completar el formulario"
                style={{ whiteSpace: 'nowrap' }}
              >
                {padron.isPending ? <CSpinner size="sm" /> : 'Buscar'}
              </CButton>
            </div>
            {padronError && <div className="text-danger small mt-1">{padronError}</div>}
            {padron.isSuccess && !padronError && (
              <div className="text-success small mt-1">Datos traídos del padrón de ARCA.</div>
            )}
          </div>
          <div className="mb-3">
            <CFormLabel htmlFor="nombre">Nombre / Razón social *</CFormLabel>
            <CFormInput id="nombre" invalid={!!errors.nombre} {...register('nombre')} />
            {errors.nombre && <div className="text-danger small mt-1">{errors.nombre.message}</div>}
          </div>
          <div className="mb-3">
            <CFormLabel htmlFor="email">Email</CFormLabel>
            <CFormInput id="email" type="email" invalid={!!errors.email} {...register('email')} />
            {errors.email && <div className="text-danger small mt-1">{errors.email.message}</div>}
          </div>
          <div className="mb-3">
            <CFormLabel htmlFor="domicilio">Domicilio</CFormLabel>
            <CFormInput id="domicilio" {...register('domicilio')} />
          </div>
          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="localidad">Localidad</CFormLabel>
              <CFormInput id="localidad" {...register('localidad')} />
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="telefono">Teléfono</CFormLabel>
              <CFormInput id="telefono" {...register('telefono')} />
            </div>
          </div>
        </CModalBody>
        <CModalFooter>
          <CButton color="secondary" variant="outline" onClick={onClose}>
            Cancelar
          </CButton>
          <CButton type="submit" color="primary" disabled={saving}>
            {saving ? 'Guardando…' : 'Guardar'}
          </CButton>
        </CModalFooter>
      </CForm>
    </CModal>
  )
}
