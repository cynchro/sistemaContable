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
  CFormTextarea,
  CButton,
} from '@coreui/react'
import type { Concepto, ConceptoInput } from '../../api/sueldos'

const schema = z.object({
  codigo: z.string().min(1, 'Obligatorio'),
  descripcion: z.string().min(1, 'Obligatorio'),
  formula: z.string().optional(),
  tipo: z.string().optional(),
  orden: z.string().optional(),
  imprimir: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  concepto: Concepto | null
  saving: boolean
  errorMsg?: string | null
  onClose: () => void
  onSubmit: (v: ConceptoInput) => void
}

export default function ConceptoFormModal({ visible, concepto, saving, errorMsg, onClose, onSubmit }: Props) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (visible) {
      reset({
        codigo: concepto?.codigo ?? '',
        descripcion: concepto?.descripcion ?? '',
        formula: concepto?.formula ?? '',
        tipo: concepto?.tipo != null ? String(concepto.tipo) : '1',
        orden: concepto?.orden != null ? String(concepto.orden) : '',
        imprimir: concepto?.imprimir ?? 'S',
      })
    }
  }, [visible, concepto, reset])

  const submit = (v: FormValues) =>
    onSubmit({
      codigo: v.codigo,
      descripcion: v.descripcion,
      formula: v.formula || null,
      tipo: v.tipo ? Number(v.tipo) : null,
      orden: v.orden ? Number(v.orden) : null,
      imprimir: v.imprimir || 'S',
    })

  return (
    <CModal visible={visible} onClose={onClose} alignment="center" size="lg">
      <CModalHeader>
        <CModalTitle>{concepto ? 'Editar concepto' : 'Nuevo concepto'}</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(submit)} noValidate>
        <CModalBody>
          {errorMsg && <div className="text-danger small mb-2">{errorMsg}</div>}
          <div className="row">
            <div className="col-md-3 mb-3">
              <CFormLabel htmlFor="codigo">Código *</CFormLabel>
              <CFormInput id="codigo" invalid={!!errors.codigo} {...register('codigo')} />
              {errors.codigo && <div className="text-danger small mt-1">{errors.codigo.message}</div>}
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="descripcion">Descripción *</CFormLabel>
              <CFormInput id="descripcion" invalid={!!errors.descripcion} {...register('descripcion')} />
              {errors.descripcion && <div className="text-danger small mt-1">{errors.descripcion.message}</div>}
            </div>
            <div className="col-md-3 mb-3">
              <CFormLabel htmlFor="tipo">Tipo</CFormLabel>
              <CFormSelect id="tipo" {...register('tipo')}>
                <option value="1">Remunerativo</option>
                <option value="2">No remunerativo</option>
                <option value="3">Descuento</option>
              </CFormSelect>
            </div>
          </div>
          <div className="mb-3">
            <CFormLabel htmlFor="formula">Fórmula</CFormLabel>
            <CFormTextarea id="formula" rows={2} {...register('formula')} placeholder="Ej: BASICO*ANTIG/100" />
            <div className="text-body-secondary small mt-1">
              Variables: BASICO, ANTIG, CAN, IMP, NOREM. Referencias a otros conceptos: <code>NNN#</code>.
            </div>
          </div>
          <div className="row">
            <div className="col-md-3 mb-3">
              <CFormLabel htmlFor="orden">Orden</CFormLabel>
              <CFormInput id="orden" inputMode="numeric" {...register('orden')} />
            </div>
            <div className="col-md-3 mb-3">
              <CFormLabel htmlFor="imprimir">Imprime</CFormLabel>
              <CFormSelect id="imprimir" {...register('imprimir')}>
                <option value="S">Sí</option>
                <option value="N">No</option>
              </CFormSelect>
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
