import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
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
} from '@coreui/react'
import type { Empresa, EmpresaInput } from '../../api/empresas'

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
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (visible) {
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
            <CFormLabel htmlFor="nombre">Nombre / Razón social *</CFormLabel>
            <CFormInput id="nombre" invalid={!!errors.nombre} {...register('nombre')} />
            {errors.nombre && <div className="text-danger small mt-1">{errors.nombre.message}</div>}
          </div>
          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="cuit">CUIT</CFormLabel>
              <CFormInput id="cuit" {...register('cuit')} />
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="email">Email</CFormLabel>
              <CFormInput id="email" type="email" invalid={!!errors.email} {...register('email')} />
              {errors.email && <div className="text-danger small mt-1">{errors.email.message}</div>}
            </div>
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
