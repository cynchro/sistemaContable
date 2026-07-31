import { useEffect, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
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
import { listCuentas } from '../../../api/cuentas'
import type { Sujeto, SujetoInput } from '../../../api/sujetos'

const schema = z.object({
  nombre: z.string().min(1, 'El nombre es obligatorio'),
  cuit: z.string().min(1, 'El CUIT es obligatorio (es la clave del padrón único)'),
  condicion_iva_id: z.string().optional(),
  provincia_id: z.string().optional(),
  domicilio: z.string().optional(),
  localidad: z.string().optional(),
  telefono: z.string().optional(),
  ingresos_brutos: z.string().optional(),
  cp: z.string().optional(),
  cai: z.string().optional(),
  fecha_cai: z.string().optional(),
  cais: z.array(z.object({ numero: z.string(), vencimiento: z.string() })),
  cuenta_id: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

// Campos de fecha opcionales: un '' del form no es una fecha válida para MySQL (columna DATE
// nullable) — hay que mandar null, no ''. Mismo patrón que ya usan Compra/VentaFormModal.
const strOrNull = (v?: string) => (v && v.trim() !== '' ? v : null)

interface Props {
  visible: boolean
  sujeto: Sujeto | null
  esProveedor: boolean
  empresaId: number
  saving: boolean
  onClose: () => void
  onSubmit: (values: SujetoInput) => void
}

export default function SujetoFormModal({ visible, sujeto, esProveedor, empresaId, saving, onClose, onSubmit }: Props) {
  const { data: condiciones } = useQuery({
    queryKey: ['catalogo', 'condiciones-iva'],
    queryFn: () => listCatalogo('condiciones-iva'),
  })
  const { data: provincias } = useQuery({
    queryKey: ['catalogo', 'provincias'],
    queryFn: () => listCatalogo('provincias'),
  })
  // Cuenta contable por defecto (documento "Satélite Visual IVA" §5): solo aplica a proveedores
  // ya existentes (la cuenta vive en iva_sujeto_empresas, que recién se crea al activar el
  // sujeto en esta empresa — no hay nada que editar todavía en el alta).
  const { data: cuentas } = useQuery({
    queryKey: ['cuentas', empresaId],
    queryFn: () => listCuentas(empresaId),
    enabled: esProveedor && !!sujeto,
  })

  const {
    register,
    handleSubmit,
    reset,
    setValue,
    getValues,
    control,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })
  const { fields: caiFields, append: caiAppend, remove: caiRemove } = useFieldArray({ control, name: 'cais' })

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
        cais: sujeto?.cais ?? [],
        cuenta_id: sujeto?.cuenta_id != null ? String(sujeto.cuenta_id) : '',
      })
    }
  }, [visible, sujeto, reset])

  const submit = (v: FormValues) =>
    onSubmit({
      ...v,
      condicion_iva_id: v.condicion_iva_id ? Number(v.condicion_iva_id) : null,
      provincia_id: v.provincia_id ? Number(v.provincia_id) : null,
      fecha_cai: strOrNull(v.fecha_cai),
      // Solo los proveedores tienen lista de CAI; se descartan las filas vacías.
      cais: esProveedor ? v.cais.filter((c) => c.numero.trim() !== '') : [],
      // Solo tiene efecto al editar (el alta la ignora, ver api/sujetos.ts).
      cuenta_id: v.cuenta_id ? Number(v.cuenta_id) : null,
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
              <CFormLabel htmlFor="cuit">CUIT * (clave del padrón único)</CFormLabel>
              <div className="d-flex gap-2">
                <CFormInput id="cuit" invalid={!!errors.cuit} {...register('cuit')} />
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
              {errors.cuit && <div className="text-danger small mt-1">{errors.cuit.message}</div>}
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
          {esProveedor && sujeto && (
            <div className="row">
              <div className="col-md-6 mb-3">
                <CFormLabel htmlFor="cuenta_id">Cuenta contable por defecto (compras)</CFormLabel>
                <CFormSelect id="cuenta_id" {...register('cuenta_id')}>
                  <option value="">— Sin regla (se pide al cargar cada compra) —</option>
                  {cuentas?.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.codigo ? `${c.codigo} — ${c.nombre}` : c.nombre}
                    </option>
                  ))}
                </CFormSelect>
                <div className="text-muted small mt-1">
                  Se precarga en las compras de este proveedor cuando la línea no trae una cuenta
                  propia. Es específica de esta empresa (otras empresas pueden tener otra).
                </div>
              </div>
            </div>
          )}
          {esProveedor && (
            <div className="mb-3">
              <div className="d-flex justify-content-between align-items-center mb-2">
                <CFormLabel className="mb-0">CAI adicionales (hasta 5)</CFormLabel>
                <CButton
                  type="button"
                  color="primary"
                  variant="outline"
                  size="sm"
                  disabled={caiFields.length >= 5}
                  onClick={() => caiAppend({ numero: '', vencimiento: '' })}
                >
                  + Agregar CAI
                </CButton>
              </div>
              {caiFields.map((f, i) => (
                <div className="row g-2 mb-2 align-items-center" key={f.id}>
                  <div className="col">
                    <CFormInput
                      placeholder="Número de CAI"
                      {...register(`cais.${i}.numero`)}
                    />
                  </div>
                  <div className="col-auto">
                    <CFormInput type="date" {...register(`cais.${i}.vencimiento`)} />
                  </div>
                  <div className="col-auto">
                    <CButton type="button" color="danger" variant="ghost" size="sm" onClick={() => caiRemove(i)}>
                      ✕
                    </CButton>
                  </div>
                </div>
              ))}
            </div>
          )}
          <div className="text-muted small">
            Este sujeto queda en el padrón único del estudio: si ya existe otro con el mismo
            CUIT en otra empresa, se reutiliza (no se duplica).
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
