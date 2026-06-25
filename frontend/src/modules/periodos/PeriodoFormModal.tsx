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
import type { PeriodoInput } from '../../api/periodos'

const schema = z.object({
  nombre: z.string().min(1, 'El nombre es obligatorio'),
  fecha_ini: z.string().min(1, 'Indicá la fecha de inicio'),
  fecha_fin: z.string().min(1, 'Indicá la fecha de fin'),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  saving: boolean
  onClose: () => void
  onSubmit: (values: PeriodoInput) => void
}

export default function PeriodoFormModal({ visible, saving, onClose, onSubmit }: Props) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (visible) {
      reset({ nombre: '', fecha_ini: '', fecha_fin: '' })
    }
  }, [visible, reset])

  return (
    <CModal visible={visible} onClose={onClose} alignment="center">
      <CModalHeader>
        <CModalTitle>Nuevo período</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(onSubmit)} noValidate>
        <CModalBody>
          <div className="mb-3">
            <CFormLabel htmlFor="nombre">Nombre</CFormLabel>
            <CFormInput id="nombre" placeholder="Ej: 2026-01" invalid={!!errors.nombre} {...register('nombre')} />
            {errors.nombre && <div className="text-danger small mt-1">{errors.nombre.message}</div>}
          </div>
          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="fecha_ini">Desde</CFormLabel>
              <CFormInput id="fecha_ini" type="date" invalid={!!errors.fecha_ini} {...register('fecha_ini')} />
              {errors.fecha_ini && <div className="text-danger small mt-1">{errors.fecha_ini.message}</div>}
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="fecha_fin">Hasta</CFormLabel>
              <CFormInput id="fecha_fin" type="date" invalid={!!errors.fecha_fin} {...register('fecha_fin')} />
              {errors.fecha_fin && <div className="text-danger small mt-1">{errors.fecha_fin.message}</div>}
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
