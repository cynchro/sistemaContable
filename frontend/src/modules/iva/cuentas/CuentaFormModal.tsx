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
import type { Cuenta, CuentaInput } from '../../../api/cuentas'

const schema = z.object({
  nombre: z.string().min(1, 'El nombre es obligatorio'),
  codigo: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  cuenta: Cuenta | null
  saving: boolean
  onClose: () => void
  onSubmit: (values: CuentaInput) => void
}

export default function CuentaFormModal({ visible, cuenta, saving, onClose, onSubmit }: Props) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (visible) {
      reset({ nombre: cuenta?.nombre ?? '', codigo: cuenta?.codigo ?? '' })
    }
  }, [visible, cuenta, reset])

  const submit = (v: FormValues) => onSubmit({ nombre: v.nombre, codigo: v.codigo || null })

  return (
    <CModal visible={visible} onClose={onClose} alignment="center">
      <CModalHeader>
        <CModalTitle>{cuenta ? 'Editar cuenta' : 'Nueva cuenta'}</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(submit)} noValidate>
        <CModalBody>
          <div className="row">
            <div className="col-4 mb-3">
              <CFormLabel htmlFor="codigo">Código</CFormLabel>
              <CFormInput id="codigo" {...register('codigo')} />
            </div>
            <div className="col-8 mb-3">
              <CFormLabel htmlFor="nombre">Nombre *</CFormLabel>
              <CFormInput id="nombre" invalid={!!errors.nombre} {...register('nombre')} />
              {errors.nombre && <div className="text-danger small mt-1">{errors.nombre.message}</div>}
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
