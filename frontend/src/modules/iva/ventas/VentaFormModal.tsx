import { useEffect } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery } from '@tanstack/react-query'
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
  CAlert,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
} from '@coreui/react'
import { listCatalogo, listTiposRetencion } from '../../../api/catalogos'
import { listSujetos } from '../../../api/sujetos'
import { listActividades } from '../../../api/actividades'
import { listRubros } from '../../../api/rubros'
import { getVenta, type VentaInput } from '../../../api/ventas'
import SujetoTypeahead from '../SujetoTypeahead'

/** Alícuotas habilitadas por AFIP (id WSFE → %). El motor usa el %. */
const ALICUOTAS = ['0', '10.5', '21', '27', '2.5', '5']

const lineaSchema = z.object({
  neto_gravado: z.string().min(1, 'Requerido'),
  iva_alicuota: z.string().min(1, 'Requerido'),
})

const percepcionSchema = z.object({
  tipo_retencion_id: z.string().min(1, 'Elegí un tipo'),
  alicuota: z.string().optional(),
  importe: z.string().optional(),
  provincia_id: z.string().optional(),
})

const asociadoSchema = z.object({
  tipo_comprobante_id: z.string().optional(),
  letra: z.string().optional(),
  punto_venta: z.string().optional(),
  numero: z.string().optional(),
  cuit: z.string().optional(),
  fecha: z.string().optional(),
})

const schema = z.object({
  fecha: z.string().min(1, 'La fecha es obligatoria'),
  tipo_comprobante_id: z.string().optional(),
  tipo_operacion_venta_id: z.string().optional(),
  tipo_documento_id: z.string().optional(),
  cliente_id: z.string().optional(),
  cliente_nombre: z.string().optional(),
  cuit: z.string().optional(),
  condicion_iva_id: z.string().optional(),
  provincia_id: z.string().optional(),
  rubro_id: z.string().optional(),
  letra: z.string().optional(),
  punto_venta: z.string().optional(),
  numero: z.string().optional(),
  numero_fin: z.string().optional(),
  cai: z.string().optional(),
  fecha_cai: z.string().optional(),
  neto_no_grav: z.string().optional(),
  exento: z.string().optional(),
  imp_interno: z.string().optional(),
  tipo_moneda_id: z.string().optional(),
  tipo_cambio: z.string().optional(),
  campo_auxiliar: z.string().optional(),
  actividad_id: z.string().optional(),
  es_bien_uso: z.boolean().optional(),
  discriminaciones: z.array(lineaSchema).min(1, 'Agregá al menos una línea de IVA'),
  percepciones: z.array(percepcionSchema),
  comprobantes_asociados: z.array(asociadoSchema),
})
type FormValues = z.infer<typeof schema>

const VACIO: FormValues = {
  fecha: '',
  tipo_comprobante_id: '',
  tipo_operacion_venta_id: '',
  tipo_documento_id: '',
  cliente_id: '',
  cliente_nombre: '',
  cuit: '',
  condicion_iva_id: '',
  provincia_id: '',
  rubro_id: '',
  letra: '',
  punto_venta: '',
  numero: '',
  numero_fin: '',
  cai: '',
  fecha_cai: '',
  neto_no_grav: '',
  exento: '',
  imp_interno: '',
  tipo_moneda_id: '',
  tipo_cambio: '',
  campo_auxiliar: '',
  actividad_id: '',
  es_bien_uso: false,
  discriminaciones: [{ neto_gravado: '', iva_alicuota: '21' }],
  percepciones: [],
  comprobantes_asociados: [],
}

interface Props {
  visible: boolean
  empresaId: number
  periodoId: number
  ventaId: number | null
  saving: boolean
  errorMsg?: string | null
  /** Fecha a pre-cargar en una venta nueva (última cargada del período). */
  ultimaFecha?: string
  onClose: () => void
  onSubmit: (values: VentaInput) => void
}

const num = (v?: string) => (v && v.trim() !== '' ? Number(v) : null)
const str = (v?: string) => (v && v.trim() !== '' ? v : null)

export default function VentaFormModal({
  visible,
  empresaId,
  periodoId,
  ventaId,
  saving,
  errorMsg,
  ultimaFecha,
  onClose,
  onSubmit,
}: Props) {
  const { data: tiposComprobante } = useQuery({
    queryKey: ['catalogo', 'tipos-comprobante'],
    queryFn: () => listCatalogo('tipos-comprobante'),
  })
  const { data: tiposOperacion } = useQuery({
    queryKey: ['catalogo', 'tipos-operacion-venta'],
    queryFn: () => listCatalogo('tipos-operacion-venta'),
  })
  const { data: condiciones } = useQuery({
    queryKey: ['catalogo', 'condiciones-iva'],
    queryFn: () => listCatalogo('condiciones-iva'),
  })
  const { data: provincias } = useQuery({
    queryKey: ['catalogo', 'provincias'],
    queryFn: () => listCatalogo('provincias'),
  })
  const { data: tiposDocumento } = useQuery({
    queryKey: ['catalogo', 'tipos-documento'],
    queryFn: () => listCatalogo('tipos-documento'),
  })
  const { data: tiposMoneda } = useQuery({
    queryKey: ['catalogo', 'tipos-moneda'],
    queryFn: () => listCatalogo('tipos-moneda'),
  })
  const { data: tiposRetencion } = useQuery({
    queryKey: ['catalogo', 'tipos-retencion'],
    queryFn: () => listTiposRetencion(),
  })
  const { data: clientes } = useQuery({
    queryKey: ['clientes', empresaId],
    queryFn: () => listSujetos('clientes', empresaId),
    enabled: visible,
  })
  const { data: actividades } = useQuery({
    queryKey: ['actividades', empresaId],
    queryFn: () => listActividades(empresaId),
    enabled: visible,
  })
  const { data: rubros } = useQuery({ queryKey: ['rubros'], queryFn: () => listRubros(), enabled: visible })

  // Al editar, traemos la venta completa (cabecera + líneas) para precargar.
  const {
    data: detalle,
    isLoading: cargando,
  } = useQuery({
    queryKey: ['venta', empresaId, periodoId, ventaId],
    queryFn: () => getVenta(empresaId, periodoId, ventaId as number),
    enabled: visible && ventaId != null,
  })

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    control,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: VACIO })

  const { fields, append, remove } = useFieldArray({ control, name: 'discriminaciones' })
  const {
    fields: percFields,
    append: percAppend,
    remove: percRemove,
  } = useFieldArray({ control, name: 'percepciones' })
  const {
    fields: asocFields,
    append: asocAppend,
    remove: asocRemove,
  } = useFieldArray({ control, name: 'comprobantes_asociados' })

  useEffect(() => {
    if (!visible) return
    if (ventaId == null) {
      reset({ ...VACIO, fecha: ultimaFecha ?? '' })
    } else if (detalle) {
      reset({
        fecha: detalle.fecha ?? '',
        tipo_comprobante_id: detalle.tipo_comprobante_id != null ? String(detalle.tipo_comprobante_id) : '',
        tipo_operacion_venta_id:
          detalle.tipo_operacion_venta_id != null ? String(detalle.tipo_operacion_venta_id) : '',
        tipo_documento_id: detalle.tipo_documento_id != null ? String(detalle.tipo_documento_id) : '',
        cliente_id: detalle.cliente_id != null ? String(detalle.cliente_id) : '',
        cliente_nombre: detalle.cliente_nombre ?? '',
        cuit: detalle.cuit ?? '',
        condicion_iva_id: detalle.condicion_iva_id != null ? String(detalle.condicion_iva_id) : '',
        provincia_id: detalle.provincia_id != null ? String(detalle.provincia_id) : '',
        rubro_id: detalle.rubro_id != null ? String(detalle.rubro_id) : '',
        letra: detalle.letra ?? '',
        punto_venta: detalle.punto_venta != null ? String(detalle.punto_venta) : '',
        numero: detalle.numero != null ? String(detalle.numero) : '',
        numero_fin: detalle.numero_fin != null ? String(detalle.numero_fin) : '',
        cai: detalle.cai ?? '',
        fecha_cai: detalle.fecha_cai ?? '',
        neto_no_grav: detalle.neto_no_grav ?? '',
        exento: detalle.exento ?? '',
        imp_interno: detalle.imp_interno ?? '',
        tipo_moneda_id: detalle.tipo_moneda_id != null ? String(detalle.tipo_moneda_id) : '',
        tipo_cambio: detalle.tipo_cambio ?? '',
        campo_auxiliar: detalle.campo_auxiliar ?? '',
        actividad_id: detalle.actividad_id != null ? String(detalle.actividad_id) : '',
        es_bien_uso: detalle.es_bien_uso === 'S',
        discriminaciones:
          detalle.discriminaciones.length > 0
            ? detalle.discriminaciones.map((d) => ({
                neto_gravado: String(d.neto_gravado),
                iva_alicuota: String(Number(d.iva_alicuota)),
              }))
            : [{ neto_gravado: '', iva_alicuota: '21' }],
        percepciones: (detalle.percepciones ?? []).map((p) => ({
          tipo_retencion_id: p.tipo_retencion_id != null ? String(p.tipo_retencion_id) : '',
          alicuota: p.alicuota != null ? String(Number(p.alicuota)) : '',
          importe: p.importe != null ? String(p.importe) : '',
          provincia_id: p.provincia_id != null ? String(p.provincia_id) : '',
        })),
        comprobantes_asociados: (detalle.comprobantes_asociados ?? []).map((a) => ({
          tipo_comprobante_id: a.tipo_comprobante_id != null ? String(a.tipo_comprobante_id) : '',
          letra: a.letra ?? '',
          punto_venta: a.punto_venta != null ? String(a.punto_venta) : '',
          numero: a.numero != null ? String(a.numero) : '',
          cuit: a.cuit ?? '',
          fecha: a.fecha ?? '',
        })),
      })
    }
  }, [visible, ventaId, detalle, reset, ultimaFecha])

  const lineas = watch('discriminaciones')
  const esFacturaC = (watch('letra') ?? '').trim().toUpperCase() === 'C'
  const netoNoGrav = watch('neto_no_grav')
  const exento = watch('exento')
  const impInterno = watch('imp_interno')

  // Vista previa del total (estimada; el total oficial lo calcula el motor del backend).
  const totalEstimado = (() => {
    let neto = 0
    let iva = 0
    for (const l of lineas ?? []) {
      const n = Number(l.neto_gravado) || 0
      const a = Number(l.iva_alicuota) || 0
      neto += n
      iva += (n * a) / 100
    }
    const extra = (Number(netoNoGrav) || 0) + (Number(exento) || 0) + (Number(impInterno) || 0)
    return neto + iva + extra
  })()

  const submit = (v: FormValues) =>
    onSubmit({
      fecha: v.fecha,
      tipo_comprobante_id: num(v.tipo_comprobante_id),
      tipo_operacion_venta_id: num(v.tipo_operacion_venta_id),
      tipo_documento_id: num(v.tipo_documento_id),
      cliente_id: num(v.cliente_id),
      cliente_nombre: str(v.cliente_nombre),
      cuit: str(v.cuit),
      condicion_iva_id: num(v.condicion_iva_id),
      provincia_id: num(v.provincia_id),
      rubro_id: num(v.rubro_id),
      letra: str(v.letra),
      punto_venta: str(v.punto_venta),
      numero: str(v.numero),
      numero_fin: str(v.numero_fin),
      cai: str(v.cai),
      fecha_cai: str(v.fecha_cai),
      neto_no_grav: str(v.neto_no_grav),
      exento: str(v.exento),
      imp_interno: str(v.imp_interno),
      tipo_moneda_id: num(v.tipo_moneda_id),
      tipo_cambio: str(v.tipo_cambio),
      campo_auxiliar: str(v.campo_auxiliar),
      actividad_id: v.actividad_id ? Number(v.actividad_id) : null,
      es_bien_uso: v.es_bien_uso ? 'S' : 'N',
      discriminaciones: v.discriminaciones.map((d) => ({
        neto_gravado: d.neto_gravado,
        iva_alicuota: d.iva_alicuota,
      })),
      percepciones: v.percepciones
        .filter((p) => p.tipo_retencion_id)
        .map((p) => ({
          tipo_retencion_id: Number(p.tipo_retencion_id),
          alicuota: str(p.alicuota),
          importe: str(p.importe),
          provincia_id: p.provincia_id ? Number(p.provincia_id) : null,
        })),
      comprobantes_asociados: v.comprobantes_asociados
        .filter((a) => a.punto_venta && a.numero)
        .map((a) => ({
          tipo_comprobante_id: num(a.tipo_comprobante_id),
          letra: str(a.letra),
          punto_venta: a.punto_venta as string,
          numero: a.numero as string,
          cuit: str(a.cuit),
          fecha: str(a.fecha),
        })),
    })

  const titulo = ventaId == null ? 'Nueva venta' : 'Editar venta'
  const mostrarForm = ventaId == null || !!detalle

  return (
    <CModal visible={visible} onClose={onClose} alignment="center" size="xl">
      <CModalHeader>
        <CModalTitle>{titulo}</CModalTitle>
      </CModalHeader>
      <CForm onSubmit={handleSubmit(submit)} noValidate>
        <CModalBody>
          {errorMsg && <CAlert color="danger">{errorMsg}</CAlert>}
          {cargando && (
            <div className="text-center py-4">
              <CSpinner />
            </div>
          )}
          {mostrarForm && (
            <>
              <div className="row">
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="fecha">Fecha *</CFormLabel>
                  <CFormInput id="fecha" type="date" invalid={!!errors.fecha} {...register('fecha')} />
                  {errors.fecha && <div className="text-danger small mt-1">{errors.fecha.message}</div>}
                </div>
                <div className="col-md-5 mb-3">
                  <CFormLabel htmlFor="tipo_comprobante_id">Tipo de comprobante</CFormLabel>
                  <CFormSelect id="tipo_comprobante_id" {...register('tipo_comprobante_id')}>
                    <option value="">—</option>
                    {tiposComprobante?.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.nombre}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
                <div className="col-md-2 mb-3">
                  <CFormLabel htmlFor="letra">Letra</CFormLabel>
                  <CFormInput id="letra" maxLength={1} {...register('letra')} />
                </div>
              </div>

              <div className="row">
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="punto_venta">Punto de venta</CFormLabel>
                  <CFormInput id="punto_venta" inputMode="numeric" {...register('punto_venta')} />
                </div>
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="numero">Número</CFormLabel>
                  <CFormInput id="numero" inputMode="numeric" {...register('numero')} />
                </div>
                <div className="col-md-6 mb-3">
                  <CFormLabel htmlFor="tipo_operacion_venta_id">Tipo de operación</CFormLabel>
                  <CFormSelect id="tipo_operacion_venta_id" {...register('tipo_operacion_venta_id')}>
                    <option value="">—</option>
                    {tiposOperacion?.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.nombre}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
              </div>

              <div className="row">
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="numero_fin">Número hasta</CFormLabel>
                  <CFormInput id="numero_fin" inputMode="numeric" {...register('numero_fin')} />
                </div>
                <div className="col-md-5 mb-3">
                  <CFormLabel htmlFor="cai">CAI / CAE</CFormLabel>
                  <CFormInput id="cai" {...register('cai')} />
                </div>
                <div className="col-md-4 mb-3">
                  <CFormLabel htmlFor="fecha_cai">Vto. CAI/CAE</CFormLabel>
                  <CFormInput id="fecha_cai" type="date" {...register('fecha_cai')} />
                </div>
              </div>

              <div className="row align-items-end">
                <div className="col-md-5 mb-3">
                  <CFormLabel htmlFor="actividad_id">Actividad (IVA / IIBB)</CFormLabel>
                  <CFormSelect id="actividad_id" {...register('actividad_id')}>
                    <option value="">— Por punto de venta —</option>
                    {actividades?.map((a) => (
                      <option key={a.id} value={a.id}>
                        {a.codigo} — {a.descripcion}
                      </option>
                    ))}
                  </CFormSelect>
                  <div className="text-body-secondary small mt-1">
                    Vacío = se resuelve por el punto de venta; elegí una para forzarla en este comprobante.
                  </div>
                </div>
                <div className="col-md-4 mb-3">
                  <CFormLabel htmlFor="rubro_id">Rubro (F2002)</CFormLabel>
                  <CFormSelect id="rubro_id" {...register('rubro_id')}>
                    <option value="">—</option>
                    {rubros?.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.codigo ? `${r.codigo} — ` : ''}
                        {r.nombre}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
                <div className="col-md-3 mb-3">
                  <div className="form-check">
                    <input className="form-check-input" type="checkbox" id="es_bien_uso" {...register('es_bien_uso')} />
                    <label className="form-check-label" htmlFor="es_bien_uso">
                      Bien de uso
                    </label>
                  </div>
                </div>
              </div>

              <hr />
              <div className="row">
                <div className="col-md-9 mb-3">
                  <CFormLabel htmlFor="cliente_nombre">Cliente / Razón social</CFormLabel>
                  <SujetoTypeahead
                    id="cliente_nombre"
                    sujetos={clientes}
                    value={watch('cliente_nombre') ?? ''}
                    placeholder="Buscar por nombre o CUIT…"
                    onText={(t) => {
                      setValue('cliente_nombre', t)
                      setValue('cliente_id', '')
                    }}
                    onPick={(c) => {
                      setValue('cliente_id', String(c.id))
                      setValue('cliente_nombre', c.nombre ?? '')
                      setValue('cuit', c.cuit ?? '')
                      if (c.condicion_iva_id != null) setValue('condicion_iva_id', String(c.condicion_iva_id))
                      if (c.provincia_id != null) setValue('provincia_id', String(c.provincia_id))
                    }}
                  />
                  <div className="text-body-secondary small mt-1">
                    Elegí uno del padrón (autocompleta) o escribí un cliente ocasional.
                  </div>
                </div>
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="cuit">CUIT</CFormLabel>
                  <CFormInput id="cuit" {...register('cuit')} />
                </div>
              </div>
              <div className="row">
                <div className="col-md-4 mb-3">
                  <CFormLabel htmlFor="tipo_documento_id">Tipo de documento</CFormLabel>
                  <CFormSelect id="tipo_documento_id" {...register('tipo_documento_id')}>
                    <option value="">—</option>
                    {tiposDocumento?.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.nombre}
                      </option>
                    ))}
                  </CFormSelect>
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

              <hr />
              <div className="d-flex justify-content-between align-items-center mb-2">
                <strong>Discriminación de IVA</strong>
                <CButton
                  type="button"
                  color="primary"
                  variant="outline"
                  size="sm"
                  onClick={() => append({ neto_gravado: '', iva_alicuota: '21' })}
                >
                  + Agregar línea
                </CButton>
              </div>
              {errors.discriminaciones?.message && (
                <div className="text-danger small mb-2">{errors.discriminaciones.message}</div>
              )}
              {esFacturaC && (
                <CAlert color="info" className="py-2 small mb-2">
                  Factura C (Monotributo/Exento): no lleva IVA discriminado. Cargá el importe en{' '}
                  <strong>Neto no gravado</strong> y dejá la discriminación en cero.
                </CAlert>
              )}
              <CTable small bordered responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Neto gravado</CTableHeaderCell>
                    <CTableHeaderCell>Alícuota %</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">IVA</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {fields.map((f, i) => {
                    const n = Number(lineas?.[i]?.neto_gravado) || 0
                    const a = Number(lineas?.[i]?.iva_alicuota) || 0
                    return (
                      <CTableRow key={f.id}>
                        <CTableDataCell>
                          <CFormInput
                            size="sm"
                            inputMode="decimal"
                            invalid={!!errors.discriminaciones?.[i]?.neto_gravado}
                            {...register(`discriminaciones.${i}.neto_gravado`)}
                          />
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormSelect size="sm" {...register(`discriminaciones.${i}.iva_alicuota`)}>
                            {ALICUOTAS.map((al) => (
                              <option key={al} value={al}>
                                {al}
                              </option>
                            ))}
                          </CFormSelect>
                        </CTableDataCell>
                        <CTableDataCell className="text-end">
                          {((n * a) / 100).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                        </CTableDataCell>
                        <CTableDataCell className="text-end">
                          <CButton
                            type="button"
                            color="danger"
                            variant="ghost"
                            size="sm"
                            disabled={fields.length <= 1}
                            onClick={() => remove(i)}
                          >
                            ✕
                          </CButton>
                        </CTableDataCell>
                      </CTableRow>
                    )
                  })}
                </CTableBody>
              </CTable>

              <hr />
              <div className="d-flex justify-content-between align-items-center mb-2">
                <strong>Percepciones</strong>
                <CButton
                  type="button"
                  color="primary"
                  variant="outline"
                  size="sm"
                  onClick={() => percAppend({ tipo_retencion_id: '', alicuota: '', importe: '', provincia_id: '' })}
                >
                  + Agregar percepción
                </CButton>
              </div>
              {percFields.length > 0 && (
                <CTable small bordered responsive align="middle" className="ledger">
                  <CTableHead>
                    <CTableRow>
                      <CTableHeaderCell>Tipo</CTableHeaderCell>
                      <CTableHeaderCell>Alícuota %</CTableHeaderCell>
                      <CTableHeaderCell>Provincia</CTableHeaderCell>
                      <CTableHeaderCell className="text-end">Importe</CTableHeaderCell>
                      <CTableHeaderCell />
                    </CTableRow>
                  </CTableHead>
                  <CTableBody>
                    {percFields.map((f, i) => (
                      <CTableRow key={f.id}>
                        <CTableDataCell>
                          <CFormSelect
                            size="sm"
                            invalid={!!errors.percepciones?.[i]?.tipo_retencion_id}
                            {...register(`percepciones.${i}.tipo_retencion_id`)}
                          >
                            <option value="">— Elegí —</option>
                            {tiposRetencion?.map((t) => (
                              <option key={t.id} value={t.id}>
                                {t.nombre}
                                {t.tenant_id ? ' (propio)' : ''}
                              </option>
                            ))}
                          </CFormSelect>
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormInput
                            size="sm"
                            inputMode="decimal"
                            placeholder="del tipo"
                            {...register(`percepciones.${i}.alicuota`)}
                          />
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormSelect size="sm" {...register(`percepciones.${i}.provincia_id`)}>
                            <option value="">—</option>
                            {provincias?.map((p) => (
                              <option key={p.id} value={p.id}>
                                {p.nombre}
                              </option>
                            ))}
                          </CFormSelect>
                        </CTableDataCell>
                        <CTableDataCell className="text-end">
                          <CFormInput
                            size="sm"
                            inputMode="decimal"
                            placeholder="calculado"
                            {...register(`percepciones.${i}.importe`)}
                          />
                        </CTableDataCell>
                        <CTableDataCell className="text-end">
                          <CButton type="button" color="danger" variant="ghost" size="sm" onClick={() => percRemove(i)}>
                            ✕
                          </CButton>
                        </CTableDataCell>
                      </CTableRow>
                    ))}
                  </CTableBody>
                </CTable>
              )}
              <div className="text-body-secondary small mb-3">
                Alícuota/importe en blanco → los calcula el sistema según el tipo (integran el total).
              </div>

              <hr />
              <div className="d-flex justify-content-between align-items-center mb-2">
                <strong>Comprobantes asociados (NC/ND)</strong>
                <CButton
                  type="button"
                  color="primary"
                  variant="outline"
                  size="sm"
                  onClick={() =>
                    asocAppend({ tipo_comprobante_id: '', letra: '', punto_venta: '', numero: '', cuit: '', fecha: '' })
                  }
                >
                  + Agregar asociado
                </CButton>
              </div>
              {asocFields.length > 0 && (
                <CTable small bordered responsive align="middle" className="ledger">
                  <CTableHead>
                    <CTableRow>
                      <CTableHeaderCell>Tipo</CTableHeaderCell>
                      <CTableHeaderCell>Letra</CTableHeaderCell>
                      <CTableHeaderCell>Punto vta</CTableHeaderCell>
                      <CTableHeaderCell>Número</CTableHeaderCell>
                      <CTableHeaderCell>CUIT</CTableHeaderCell>
                      <CTableHeaderCell>Fecha</CTableHeaderCell>
                      <CTableHeaderCell />
                    </CTableRow>
                  </CTableHead>
                  <CTableBody>
                    {asocFields.map((f, i) => (
                      <CTableRow key={f.id}>
                        <CTableDataCell>
                          <CFormSelect size="sm" {...register(`comprobantes_asociados.${i}.tipo_comprobante_id`)}>
                            <option value="">—</option>
                            {tiposComprobante?.map((t) => (
                              <option key={t.id} value={t.id}>
                                {t.nombre}
                              </option>
                            ))}
                          </CFormSelect>
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormInput size="sm" maxLength={1} {...register(`comprobantes_asociados.${i}.letra`)} />
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormInput size="sm" inputMode="numeric" {...register(`comprobantes_asociados.${i}.punto_venta`)} />
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormInput size="sm" inputMode="numeric" {...register(`comprobantes_asociados.${i}.numero`)} />
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormInput size="sm" {...register(`comprobantes_asociados.${i}.cuit`)} />
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormInput size="sm" type="date" {...register(`comprobantes_asociados.${i}.fecha`)} />
                        </CTableDataCell>
                        <CTableDataCell className="text-end">
                          <CButton type="button" color="danger" variant="ghost" size="sm" onClick={() => asocRemove(i)}>
                            ✕
                          </CButton>
                        </CTableDataCell>
                      </CTableRow>
                    ))}
                  </CTableBody>
                </CTable>
              )}
              <div className="text-body-secondary small mb-3">
                Para notas de crédito/débito: referenciá la/s factura/s original/es (punto de venta y número
                obligatorios).
              </div>

              <div className="row">
                <div className="col-md-4 mb-3">
                  <CFormLabel htmlFor="neto_no_grav">Neto no gravado</CFormLabel>
                  <CFormInput id="neto_no_grav" inputMode="decimal" {...register('neto_no_grav')} />
                </div>
                <div className="col-md-4 mb-3">
                  <CFormLabel htmlFor="exento">Exento</CFormLabel>
                  <CFormInput id="exento" inputMode="decimal" {...register('exento')} />
                </div>
                <div className="col-md-4 mb-3">
                  <CFormLabel htmlFor="imp_interno">Imp. internos</CFormLabel>
                  <CFormInput id="imp_interno" inputMode="decimal" {...register('imp_interno')} />
                </div>
              </div>

              <div className="row">
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="tipo_moneda_id">Moneda</CFormLabel>
                  <CFormSelect id="tipo_moneda_id" {...register('tipo_moneda_id')}>
                    <option value="">—</option>
                    {tiposMoneda?.map((m) => (
                      <option key={m.id} value={m.id}>
                        {m.nombre}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="tipo_cambio">Cotización</CFormLabel>
                  <CFormInput id="tipo_cambio" inputMode="decimal" {...register('tipo_cambio')} />
                </div>
                <div className="col-md-6 mb-3">
                  <CFormLabel htmlFor="campo_auxiliar">Campo auxiliar</CFormLabel>
                  <CFormInput id="campo_auxiliar" {...register('campo_auxiliar')} />
                </div>
              </div>

              <div className="text-end fs-5">
                Total estimado:{' '}
                <strong>
                  {totalEstimado.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' })}
                </strong>
                <div className="text-body-secondary small">
                  El total definitivo lo calcula el sistema al guardar (incluye percepciones).
                </div>
              </div>
            </>
          )}
        </CModalBody>
        <CModalFooter>
          <CButton color="secondary" variant="outline" onClick={onClose}>
            Cancelar
          </CButton>
          <CButton type="submit" color="primary" disabled={saving || cargando}>
            {saving ? 'Guardando…' : 'Guardar'}
          </CButton>
        </CModalFooter>
      </CForm>
    </CModal>
  )
}
