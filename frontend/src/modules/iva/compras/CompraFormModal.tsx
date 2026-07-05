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
import { listCatalogo } from '../../../api/catalogos'
import { listSujetos } from '../../../api/sujetos'
import { listActividades } from '../../../api/actividades'
import { getCompra, type CompraInput } from '../../../api/compras'

/** Alícuotas habilitadas por AFIP (%). El motor usa el %. */
const ALICUOTAS = ['0', '10.5', '21', '27', '2.5', '5']

const lineaSchema = z.object({
  neto_gravado: z.string().min(1, 'Requerido'),
  iva_alicuota: z.string().min(1, 'Requerido'),
  cf_computable: z.string().optional(),
})

const schema = z.object({
  fecha: z.string().min(1, 'La fecha es obligatoria'),
  tipo_comprobante_id: z.string().optional(),
  tipo_operacion_compra_id: z.string().optional(),
  proveedor_id: z.string().optional(),
  proveedor_nombre: z.string().optional(),
  cuit: z.string().optional(),
  condicion_iva_id: z.string().optional(),
  provincia_id: z.string().optional(),
  letra: z.string().optional(),
  punto_venta: z.string().optional(),
  numero: z.string().optional(),
  neto_no_grav: z.string().optional(),
  exento: z.string().optional(),
  imp_interno: z.string().optional(),
  tipo_moneda_id: z.string().optional(),
  tipo_cambio: z.string().optional(),
  campo_auxiliar: z.string().optional(),
  actividad_id: z.string().optional(),
  concepto_dj: z.string().optional(),
  discriminaciones: z.array(lineaSchema).min(1, 'Agregá al menos una línea de IVA'),
})
type FormValues = z.infer<typeof schema>

const VACIO: FormValues = {
  fecha: '',
  tipo_comprobante_id: '',
  tipo_operacion_compra_id: '',
  proveedor_id: '',
  proveedor_nombre: '',
  cuit: '',
  condicion_iva_id: '',
  provincia_id: '',
  letra: '',
  punto_venta: '',
  numero: '',
  neto_no_grav: '',
  exento: '',
  imp_interno: '',
  tipo_moneda_id: '',
  tipo_cambio: '',
  campo_auxiliar: '',
  actividad_id: '',
  concepto_dj: '',
  discriminaciones: [{ neto_gravado: '', iva_alicuota: '21', cf_computable: '' }],
}

interface Props {
  visible: boolean
  empresaId: number
  periodoId: number
  compraId: number | null
  saving: boolean
  errorMsg?: string | null
  onClose: () => void
  onSubmit: (values: CompraInput) => void
}

const num = (v?: string) => (v && v.trim() !== '' ? Number(v) : null)
const str = (v?: string) => (v && v.trim() !== '' ? v : null)

export default function CompraFormModal({
  visible,
  empresaId,
  periodoId,
  compraId,
  saving,
  errorMsg,
  onClose,
  onSubmit,
}: Props) {
  const { data: tiposComprobante } = useQuery({
    queryKey: ['catalogo', 'tipos-comprobante'],
    queryFn: () => listCatalogo('tipos-comprobante'),
  })
  const { data: tiposOperacion } = useQuery({
    queryKey: ['catalogo', 'tipos-operacion-compra'],
    queryFn: () => listCatalogo('tipos-operacion-compra'),
  })
  const { data: condiciones } = useQuery({
    queryKey: ['catalogo', 'condiciones-iva'],
    queryFn: () => listCatalogo('condiciones-iva'),
  })
  const { data: provincias } = useQuery({
    queryKey: ['catalogo', 'provincias'],
    queryFn: () => listCatalogo('provincias'),
  })
  const { data: tiposMoneda } = useQuery({
    queryKey: ['catalogo', 'tipos-moneda'],
    queryFn: () => listCatalogo('tipos-moneda'),
  })
  const { data: proveedores } = useQuery({
    queryKey: ['proveedores', empresaId],
    queryFn: () => listSujetos('proveedores', empresaId),
    enabled: visible,
  })
  const { data: actividades } = useQuery({
    queryKey: ['actividades', empresaId],
    queryFn: () => listActividades(empresaId),
    enabled: visible,
  })

  const { data: detalle, isLoading: cargando } = useQuery({
    queryKey: ['compra', empresaId, periodoId, compraId],
    queryFn: () => getCompra(empresaId, periodoId, compraId as number),
    enabled: visible && compraId != null,
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

  useEffect(() => {
    if (!visible) return
    if (compraId == null) {
      reset(VACIO)
    } else if (detalle) {
      reset({
        fecha: detalle.fecha ?? '',
        tipo_comprobante_id: detalle.tipo_comprobante_id != null ? String(detalle.tipo_comprobante_id) : '',
        tipo_operacion_compra_id:
          detalle.tipo_operacion_compra_id != null ? String(detalle.tipo_operacion_compra_id) : '',
        proveedor_id: detalle.proveedor_id != null ? String(detalle.proveedor_id) : '',
        proveedor_nombre: detalle.proveedor_nombre ?? '',
        cuit: detalle.cuit ?? '',
        condicion_iva_id: detalle.condicion_iva_id != null ? String(detalle.condicion_iva_id) : '',
        provincia_id: detalle.provincia_id != null ? String(detalle.provincia_id) : '',
        letra: detalle.letra ?? '',
        punto_venta: detalle.punto_venta != null ? String(detalle.punto_venta) : '',
        numero: detalle.numero != null ? String(detalle.numero) : '',
        neto_no_grav: detalle.neto_no_grav ?? '',
        exento: detalle.exento ?? '',
        imp_interno: detalle.imp_interno ?? '',
        tipo_moneda_id: detalle.tipo_moneda_id != null ? String(detalle.tipo_moneda_id) : '',
        tipo_cambio: detalle.tipo_cambio ?? '',
        campo_auxiliar: detalle.campo_auxiliar ?? '',
        actividad_id: detalle.actividad_id != null ? String(detalle.actividad_id) : '',
        concepto_dj: detalle.concepto_dj != null ? String(detalle.concepto_dj) : '',
        discriminaciones:
          detalle.discriminaciones.length > 0
            ? detalle.discriminaciones.map((d) => ({
                neto_gravado: String(d.neto_gravado),
                iva_alicuota: String(Number(d.iva_alicuota)),
                cf_computable: d.cf_computable != null ? String(d.cf_computable) : '',
              }))
            : [{ neto_gravado: '', iva_alicuota: '21', cf_computable: '' }],
      })
    }
  }, [visible, compraId, detalle, reset])

  const onProveedorChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const id = e.target.value
    setValue('proveedor_id', id)
    const p = proveedores?.find((x) => String(x.id) === id)
    if (p) {
      setValue('proveedor_nombre', p.nombre ?? '')
      setValue('cuit', p.cuit ?? '')
      if (p.condicion_iva_id != null) setValue('condicion_iva_id', String(p.condicion_iva_id))
      if (p.provincia_id != null) setValue('provincia_id', String(p.provincia_id))
    }
  }

  const lineas = watch('discriminaciones')
  const netoNoGrav = watch('neto_no_grav')
  const exento = watch('exento')
  const impInterno = watch('imp_interno')

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
      tipo_operacion_compra_id: num(v.tipo_operacion_compra_id),
      proveedor_id: num(v.proveedor_id),
      proveedor_nombre: str(v.proveedor_nombre),
      cuit: str(v.cuit),
      condicion_iva_id: num(v.condicion_iva_id),
      provincia_id: num(v.provincia_id),
      letra: str(v.letra),
      punto_venta: str(v.punto_venta),
      numero: str(v.numero),
      neto_no_grav: str(v.neto_no_grav),
      exento: str(v.exento),
      imp_interno: str(v.imp_interno),
      tipo_moneda_id: num(v.tipo_moneda_id),
      tipo_cambio: str(v.tipo_cambio),
      campo_auxiliar: str(v.campo_auxiliar),
      actividad_id: v.actividad_id ? Number(v.actividad_id) : null,
      concepto_dj: v.concepto_dj ? Number(v.concepto_dj) : null,
      discriminaciones: v.discriminaciones.map((d) => ({
        neto_gravado: d.neto_gravado,
        iva_alicuota: d.iva_alicuota,
        cf_computable: str(d.cf_computable),
      })),
    })

  const titulo = compraId == null ? 'Nueva compra' : 'Editar compra'
  const mostrarForm = compraId == null || !!detalle

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
                  <CFormLabel htmlFor="tipo_operacion_compra_id">Tipo de operación</CFormLabel>
                  <CFormSelect id="tipo_operacion_compra_id" {...register('tipo_operacion_compra_id')}>
                    <option value="">—</option>
                    {tiposOperacion?.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.nombre}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
              </div>

              <hr />
              <div className="row">
                <div className="col-md-4 mb-3">
                  <CFormLabel htmlFor="proveedor_id">Proveedor</CFormLabel>
                  <CFormSelect id="proveedor_id" value={watch('proveedor_id')} onChange={onProveedorChange}>
                    <option value="">— Sin proveedor / manual —</option>
                    {proveedores?.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.nombre}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
                <div className="col-md-5 mb-3">
                  <CFormLabel htmlFor="proveedor_nombre">Nombre / Razón social</CFormLabel>
                  <CFormInput id="proveedor_nombre" {...register('proveedor_nombre')} />
                </div>
                <div className="col-md-3 mb-3">
                  <CFormLabel htmlFor="cuit">CUIT</CFormLabel>
                  <CFormInput id="cuit" {...register('cuit')} />
                </div>
              </div>
              <div className="row">
                <div className="col-md-6 mb-3">
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
                <div className="col-md-6 mb-3">
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
                <div className="col-md-6 mb-3">
                  <CFormLabel htmlFor="actividad_id">Actividad (IIBB)</CFormLabel>
                  <CFormSelect id="actividad_id" {...register('actividad_id')}>
                    <option value="">— Por punto de venta —</option>
                    {actividades?.map((a) => (
                      <option key={a.id} value={a.id}>
                        {a.codigo} — {a.descripcion}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
                <div className="col-md-6 mb-3">
                  <CFormLabel htmlFor="concepto_dj">Concepto (DJ IVA)</CFormLabel>
                  <CFormSelect id="concepto_dj" {...register('concepto_dj')}>
                    <option value="">— Bienes (default) —</option>
                    <option value="1">1 — Compras de bienes</option>
                    <option value="2">2 — Locaciones (alquileres)</option>
                    <option value="3">3 — Servicios (luz/agua/gas/tel.)</option>
                    <option value="4">4 — Inversiones en bienes de uso</option>
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
                  onClick={() => append({ neto_gravado: '', iva_alicuota: '21', cf_computable: '' })}
                >
                  + Agregar línea
                </CButton>
              </div>
              {errors.discriminaciones?.message && (
                <div className="text-danger small mb-2">{errors.discriminaciones.message}</div>
              )}
              <CTable small bordered responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Neto gravado</CTableHeaderCell>
                    <CTableHeaderCell>Alícuota %</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">IVA</CTableHeaderCell>
                    <CTableHeaderCell>CF computable</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {fields.map((f, i) => {
                    const n = Number(lineas?.[i]?.neto_gravado) || 0
                    const a = Number(lineas?.[i]?.iva_alicuota) || 0
                    const iva = (n * a) / 100
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
                          {iva.toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                        </CTableDataCell>
                        <CTableDataCell>
                          <CFormInput
                            size="sm"
                            inputMode="decimal"
                            placeholder={iva.toFixed(2)}
                            {...register(`discriminaciones.${i}.cf_computable`)}
                          />
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
              <div className="text-body-secondary small mb-3">
                CF computable: dejalo en blanco para computar el 100% del IVA de la línea.
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
