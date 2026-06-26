import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery, useMutation } from '@tanstack/react-query'
import {
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CForm,
  CFormInput,
  CFormSelect,
  CFormLabel,
  CButton,
} from '@coreui/react'
import { listCatalogo } from '../../../api/catalogos'
import { sugerenciaPadron } from '../../../api/afip'
import type { Sujeto, SujetoInput } from '../../../api/sujetos'

const schema = z.object({
  nombre: z.string().min(1, 'El nombre es obligatorio'),
  cuit: z.string().optional(),
  condicion_iva_id: z.string().optional(),
  provincia_id: z.string().optional(),
  domicilio: z.string().optional(),
  localidad: z.string().optional(),
  telefono: z.string().optional(),
  ingresos_brutos: z.string().optional(),
  cp: z.string().optional(),
  cai: z.string().optional(),
  fecha_cai: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  sujeto: Sujeto | null
  esProveedor: boolean
  saving: boolean
  onClose: () => void
  onSubmit: (values: SujetoInput) => void
}

export default function SujetoFormModal({ visible, sujeto, esProveedor, saving, onClose, onSubmit }: Props) {
  const { data: condiciones } = useQuery({
    queryKey: ['catalogo', 'condiciones-iva'],
    queryFn: () => listCatalogo('condiciones-iva'),
  })
  const { data: provincias } = useQuery({
    queryKey: ['catalogo', 'provincias'],
    queryFn: () => listCatalogo('provincias'),
  })

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

  const traerDePadron = () => {
    const cuit = (getValues('cuit') ?? '').replace(/\D/g, '')
    if (cuit.length === 11) padron.mutate(cuit)
    else setPadronError('Ingresá un CUIT de 11 dígitos para consultar el padrón.')
  }

  useEffect(() => {
    if (visible) {
      reset({
        nombre: sujeto?.nombre ?? '',
        cuit: sujeto?.cuit ?? '',
        condicion_iva_id: sujeto?.condicion_iva_id != null ? String(sujeto.condicion_iva_id) : '',
        provincia_id: sujeto?.provincia_id != null ? String(sujeto.provincia_id) : '',
        domicilio: sujeto?.domicilio ?? '',
        localidad: sujeto?.localidad ?? '',
        telefono: sujeto?.telefono ?? '',
        ingresos_brutos: sujeto?.ingresos_brutos ?? '',
        cp: sujeto?.cp ?? '',
        cai: sujeto?.cai ?? '',
        fecha_cai: sujeto?.fecha_cai ?? '',
      })
    }
  }, [visible, sujeto, reset])

  const submit = (v: FormValues) =>
    onSubmit({
      ...v,
      condicion_iva_id: v.condicion_iva_id ? Number(v.condicion_iva_id) : null,
      provincia_id: v.provincia_id ? Number(v.provincia_id) : null,
    })

  const titulo = `${sujeto ? 'Editar' : 'Nuevo'} ${esProveedor ? 'proveedor' : 'cliente'}`

  return (
    <CModal visible={visible} onClose={onClose} alignment="center" size="lg">
      <CModalHeader>
        <CModalTitle>{titulo}</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(submit)} noValidate>
        <CModalBody>
          <div className="mb-3">
            <CFormLabel htmlFor="nombre">Nombre / Razón social *</CFormLabel>
            <CFormInput id="nombre" invalid={!!errors.nombre} {...register('nombre')} />
            {errors.nombre && <div className="text-danger small mt-1">{errors.nombre.message}</div>}
          </div>
          <div className="row">
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="cuit">CUIT</CFormLabel>
              <div className="d-flex gap-2">
                <CFormInput id="cuit" {...register('cuit')} />
                <CButton
                  type="button"
                  color="info"
                  variant="outline"
                  disabled={padron.isPending}
                  onClick={traerDePadron}
                  title="Autocompletar con el padrón de ARCA"
                >
                  {padron.isPending ? '…' : 'AFIP'}
                </CButton>
              </div>
              {padronError && <div className="text-danger small mt-1">{padronError}</div>}
            </div>
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="condicion_iva_id">Condición IVA</CFormLabel>
              <CFormSelect id="condicion_iva_id" {...register('condicion_iva_id')}>
                <option value="">—</option>
                {condiciones?.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.nombre}
                  </option>
                ))}
              </CFormSelect>
            </div>
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="ingresos_brutos">Ingresos Brutos</CFormLabel>
              <CFormInput id="ingresos_brutos" {...register('ingresos_brutos')} />
            </div>
          </div>
          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="domicilio">Domicilio</CFormLabel>
              <CFormInput id="domicilio" {...register('domicilio')} />
            </div>
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="localidad">Localidad</CFormLabel>
              <CFormInput id="localidad" {...register('localidad')} />
            </div>
            <div className="col-md-2 mb-3">
              <CFormLabel htmlFor="provincia_id">Provincia</CFormLabel>
              <CFormSelect id="provincia_id" {...register('provincia_id')}>
                <option value="">—</option>
                {provincias?.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.nombre}
                  </option>
                ))}
              </CFormSelect>
            </div>
          </div>
          <div className="row">
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="telefono">Teléfono</CFormLabel>
              <CFormInput id="telefono" {...register('telefono')} />
            </div>
            {esProveedor && (
              <>
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="cp">Cód. Postal</CFormLabel>
                  <CFormInput id="cp" {...register('cp')} />
                </div>
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="cai">CAI</CFormLabel>
                  <CFormInput id="cai" {...register('cai')} />
                </div>
                <div className="col-md-2 mb-3">
                  <CFormLabel htmlFor="fecha_cai">Vto. CAI</CFormLabel>
                  <CFormInput id="fecha_cai" type="date" {...register('fecha_cai')} />
                </div>
              </>
            )}
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
