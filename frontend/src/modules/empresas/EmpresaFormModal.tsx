import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useMutation, useQuery } from '@tanstack/react-query'
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
  CFormSelect,
  CFormLabel,
  CButton,
  CSpinner,
} from '@coreui/react'
import type { Empresa, EmpresaInput } from '../../api/empresas'
import { sugerenciaPadron } from '../../api/afip'
import { listCatalogo, type CatalogoItem } from '../../api/catalogos'

const schema = z.object({
  nombre: z.string().min(1, 'El nombre es obligatorio'),
  cuit: z.string().optional(),
  email: z.string().email('Email inválido').optional().or(z.literal('')),
  domicilio: z.string().optional(),
  localidad: z.string().optional(),
  telefono: z.string().optional(),
  condicion_iva_id: z.string().optional(),
  ingresos_brutos: z.string().optional(),
  fecha_inicio_actividades: z.string().optional(),
  actividad1_id: z.string().optional(),
  actividad2_id: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  empresa: Empresa | null
  saving: boolean
  onClose: () => void
  onSubmit: (values: EmpresaInput) => void
}

/** Datos que trae el padrón pero la empresa no persiste como texto: solo se muestran. */
interface PadronInfo {
  provincia: string | null
  condicion_iva: string | null
  actividad_principal: string | null
  actividad_secundaria: string | null
}

/** Preselecciona la condición IVA del catálogo a partir del texto derivado del padrón. */
function matchCondicion(label: string | null, cats: CatalogoItem[] | undefined): string {
  if (!label || !cats) return ''
  const has = (nombre: string, txt: string) => nombre.toLowerCase().includes(txt)
  let found: CatalogoItem | undefined
  if (/monotributo/i.test(label)) found = cats.find((c) => has(c.nombre, 'monotributo'))
  else if (/exento/i.test(label)) found = cats.find((c) => has(c.nombre, 'exento'))
  else if (/responsable inscripto/i.test(label))
    found = cats.find((c) => has(c.nombre, 'responsable inscripto') && !has(c.nombre, 'no inscripto'))
  return found ? String(found.id) : ''
}

export default function EmpresaFormModal({ visible, empresa, saving, onClose, onSubmit }: Props) {
  const { data: condiciones } = useQuery({
    queryKey: ['catalogo', 'condiciones-iva'],
    queryFn: () => listCatalogo('condiciones-iva'),
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
  const [padronInfo, setPadronInfo] = useState<PadronInfo | null>(null)

  const padron = useMutation({
    mutationFn: (cuit: string) => sugerenciaPadron(cuit),
    onSuccess: (s) => {
      setPadronError(null)
      if (s.nombre) setValue('nombre', s.nombre)
      if (s.domicilio) setValue('domicilio', s.domicilio)
      if (s.localidad) setValue('localidad', s.localidad)
      if (s.inicio_actividad) setValue('fecha_inicio_actividades', s.inicio_actividad)
      if (s.actividad1_id != null) setValue('actividad1_id', String(s.actividad1_id))
      if (s.actividad2_id != null) setValue('actividad2_id', String(s.actividad2_id))
      const cond = matchCondicion(s.condicion_iva, condiciones)
      if (cond) setValue('condicion_iva_id', cond)
      setPadronInfo({
        provincia: s.provincia,
        condicion_iva: s.condicion_iva,
        actividad_principal: s.actividad_principal,
        actividad_secundaria: s.actividad_secundaria,
      })
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
      setPadronInfo(null)
      padron.reset()
      reset({
        nombre: empresa?.nombre ?? '',
        cuit: empresa?.cuit ?? '',
        email: empresa?.email ?? '',
        domicilio: empresa?.domicilio ?? '',
        localidad: empresa?.localidad ?? '',
        telefono: empresa?.telefono ?? '',
        condicion_iva_id: empresa?.condicion_iva_id != null ? String(empresa.condicion_iva_id) : '',
        ingresos_brutos: empresa?.ingresos_brutos ?? '',
        fecha_inicio_actividades: empresa?.fecha_inicio_actividades ?? '',
        actividad1_id: empresa?.actividad1_id != null ? String(empresa.actividad1_id) : '',
        actividad2_id: empresa?.actividad2_id != null ? String(empresa.actividad2_id) : '',
      })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, empresa, reset])

  const submit = (v: FormValues) =>
    onSubmit({
      ...v,
      condicion_iva_id: v.condicion_iva_id ? Number(v.condicion_iva_id) : null,
      fecha_inicio_actividades: v.fecha_inicio_actividades || null,
      actividad1_id: v.actividad1_id ? Number(v.actividad1_id) : null,
      actividad2_id: v.actividad2_id ? Number(v.actividad2_id) : null,
    })

  return (
    <CModal visible={visible} onClose={onClose} alignment="center" size="lg">
      <CModalHeader>
        <CModalTitle>{empresa ? 'Editar empresa' : 'Nueva empresa'}</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(submit)} noValidate>
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
              <div className="text-success small mt-1">
                Datos traídos del padrón de ARCA. El teléfono no lo publica ARCA: cargalo a mano.
              </div>
            )}
          </div>

          {padronInfo && (
            <div className="mb-3 p-2 rounded bg-body-tertiary small">
              <div className="fw-semibold mb-1">Datos de ARCA</div>
              <div className="row">
                <div className="col-md-6">
                  <span className="text-body-secondary">Provincia: </span>
                  {padronInfo.provincia ?? '—'}
                </div>
                <div className="col-md-6">
                  <span className="text-body-secondary">Condición IVA: </span>
                  {padronInfo.condicion_iva ?? '—'}
                </div>
                <div className="col-md-6">
                  <span className="text-body-secondary">Actividad principal: </span>
                  {padronInfo.actividad_principal ?? '—'}
                </div>
                <div className="col-md-6">
                  <span className="text-body-secondary">Actividad secundaria: </span>
                  {padronInfo.actividad_secundaria ?? '—'}
                </div>
              </div>
            </div>
          )}

          <div className="mb-3">
            <CFormLabel htmlFor="nombre">Nombre / Razón social *</CFormLabel>
            <CFormInput id="nombre" invalid={!!errors.nombre} {...register('nombre')} />
            {errors.nombre && <div className="text-danger small mt-1">{errors.nombre.message}</div>}
          </div>

          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="condicion_iva_id">Condición frente al IVA</CFormLabel>
              <CFormSelect id="condicion_iva_id" {...register('condicion_iva_id')}>
                <option value="">—</option>
                {condiciones?.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.nombre}
                  </option>
                ))}
              </CFormSelect>
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

          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="ingresos_brutos">Ingresos Brutos</CFormLabel>
              <CFormInput id="ingresos_brutos" {...register('ingresos_brutos')} />
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="fecha_inicio_actividades">Inicio de actividad</CFormLabel>
              <CFormInput id="fecha_inicio_actividades" type="date" {...register('fecha_inicio_actividades')} />
            </div>
          </div>

          {/* Códigos de actividad del padrón (se persisten; se muestran arriba por descripción). */}
          <input type="hidden" {...register('actividad1_id')} />
          <input type="hidden" {...register('actividad2_id')} />
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
