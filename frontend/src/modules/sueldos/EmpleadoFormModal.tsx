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
  CFormSelect,
  CButton,
} from '@coreui/react'
import type { Empleado, EmpleadoInput } from '../../api/sueldos'

const schema = z.object({
  nombres: z.string().min(1, 'Obligatorio'),
  primer_apellido: z.string().optional(),
  segundo_apellido: z.string().optional(),
  legajo: z.string().optional(),
  cuil: z.string().optional(),
  fecha_ingreso: z.string().optional(),
  basico: z.string().optional(),
  email: z.string().optional(),
  activo: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  empleado: Empleado | null
  saving: boolean
  errorMsg?: string | null
  onClose: () => void
  onSubmit: (v: EmpleadoInput) => void
}

export default function EmpleadoFormModal({ visible, empleado, saving, errorMsg, onClose, onSubmit }: Props) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (visible) {
      reset({
        nombres: empleado?.nombres ?? '',
        primer_apellido: empleado?.primer_apellido ?? '',
        segundo_apellido: empleado?.segundo_apellido ?? '',
        legajo: empleado?.legajo != null ? String(empleado.legajo) : '',
        cuil: empleado?.cuil ?? '',
        fecha_ingreso: empleado?.fecha_ingreso ?? '',
        basico: empleado?.basico ?? '',
        email: empleado?.email ?? '',
        activo: empleado?.activo ?? 'S',
      })
    }
  }, [visible, empleado, reset])

  const submit = (v: FormValues) =>
    onSubmit({
      nombres: v.nombres,
      primer_apellido: v.primer_apellido || null,
      segundo_apellido: v.segundo_apellido || null,
      legajo: v.legajo ? Number(v.legajo) : null,
      cuil: v.cuil || null,
      fecha_ingreso: v.fecha_ingreso || null,
      basico: v.basico || null,
      email: v.email || null,
      activo: v.activo || 'S',
    })

  return (
    <CModal visible={visible} onClose={onClose} alignment="center" size="lg">
      <CModalHeader>
        <CModalTitle>{empleado ? 'Editar legajo' : 'Nuevo legajo'}</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(submit)} noValidate>
        <CModalBody>
          {errorMsg && <div className="text-danger small mb-2">{errorMsg}</div>}
          <div className="row">
            <div className="col-md-2 mb-3">
              <CFormLabel htmlFor="legajo">Legajo</CFormLabel>
              <CFormInput id="legajo" inputMode="numeric" {...register('legajo')} />
            </div>
            <div className="col-md-5 mb-3">
              <CFormLabel htmlFor="nombres">Nombres *</CFormLabel>
              <CFormInput id="nombres" invalid={!!errors.nombres} {...register('nombres')} />
              {errors.nombres && <div className="text-danger small mt-1">{errors.nombres.message}</div>}
            </div>
            <div className="col-md-5 mb-3">
              <CFormLabel htmlFor="cuil">CUIL</CFormLabel>
              <CFormInput id="cuil" {...register('cuil')} />
            </div>
          </div>
          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="primer_apellido">Primer apellido</CFormLabel>
              <CFormInput id="primer_apellido" {...register('primer_apellido')} />
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="segundo_apellido">Segundo apellido</CFormLabel>
              <CFormInput id="segundo_apellido" {...register('segundo_apellido')} />
            </div>
          </div>
          <div className="row">
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="fecha_ingreso">Fecha de ingreso</CFormLabel>
              <CFormInput id="fecha_ingreso" type="date" {...register('fecha_ingreso')} />
            </div>
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="basico">Básico</CFormLabel>
              <CFormInput id="basico" inputMode="decimal" {...register('basico')} />
            </div>
            <div className="col-md-2 mb-3">
              <CFormLabel htmlFor="activo">Estado</CFormLabel>
              <CFormSelect id="activo" {...register('activo')}>
                <option value="S">Activo</option>
                <option value="N">Inactivo</option>
              </CFormSelect>
            </div>
            <div className="col-md-2 mb-3">
              <CFormLabel htmlFor="email">Email</CFormLabel>
              <CFormInput id="email" {...register('email')} />
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
