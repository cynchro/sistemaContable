import { useEffect, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
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
import { sugerenciaSige } from '../../api/sige'
import { buscarSujetoPorCuit } from '../../api/padronUnico'
import { useCuitLookup } from '../../hooks/useCuitLookup'
import { listCatalogo, type CatalogoItem } from '../../api/catalogos'
import ActividadSelect from './ActividadSelect'

const schema = z.object({
  nombre: z.string().min(1, 'El nombre es obligatorio'),
  cuit: z.string().optional(),
  email: z.string().email('Email inválido').optional().or(z.literal('')),
  domicilio: z.string().optional(),
  localidad: z.string().optional(),
  provincia_id: z.string().optional(),
  telefono: z.string().optional(),
  condicion_iva_id: z.string().optional(),
  ingresos_brutos: z.string().optional(),
  fecha_inicio_actividades: z.string().optional(),
  actividad1_id: z.string().optional(),
  actividad2_id: z.string().optional(),
  contacto: z.string().optional(),
  tipo_persona: z.string().optional(),
  inscripcion: z.string().optional(),
  contabilidad: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  visible: boolean
  empresa: Empresa | null
  saving: boolean
  onClose: () => void
  onSubmit: (values: EmpresaInput) => void
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

/** Formatea "ahora" como DATETIME de MySQL (Y-m-d H:i:s) — el validador del backend no acepta ISO 8601. */
function ahoraComoDatetimeMysql(): string {
  return new Date().toISOString().slice(0, 19).replace('T', ' ')
}

/** Resuelve el id de actividad del catálogo a partir del código AFIP (NAES) del padrón. */
function actividadIdPorCodigo(codigo: number | null, cats: CatalogoItem[] | undefined): string {
  if (codigo == null || !cats) return ''
  const found = cats.find((a) => a.codigo === String(codigo))
  return found ? String(found.id) : ''
}

export default function EmpresaFormModal({ visible, empresa, saving, onClose, onSubmit }: Props) {
  const { data: condiciones } = useQuery({
    queryKey: ['catalogo', 'condiciones-iva'],
    queryFn: () => listCatalogo('condiciones-iva'),
  })
  const { data: provincias } = useQuery({
    queryKey: ['catalogo', 'provincias'],
    queryFn: () => listCatalogo('provincias'),
  })
  const { data: actividades } = useQuery({
    queryKey: ['catalogo', 'actividades'],
    queryFn: () => listCatalogo('actividades'),
    staleTime: Infinity,
  })

  const {
    register,
    handleSubmit,
    reset,
    setValue,
    getValues,
    watch,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  // La condición IVA (select) sólo puede fijarse cuando ya se renderizaron sus <option>;
  // este flag reaplica el valor guardado una vez que carga el catálogo (evita que quede vacío al editar).
  const catalogoAplicado = useRef(false)

  // Datos que no son parte del formulario editable: trazabilidad de que el alta vino del SIGE.
  const [sigeMeta, setSigeMeta] = useState<{ sige_persona_id: number | null; sige_synced_at: string | null }>({
    sige_persona_id: null,
    sige_synced_at: null,
  })

  const sige = useCuitLookup(sugerenciaSige, {
    notFoundMessage: 'No se pudo consultar el SIGE.',
    onSuccess: (s) => {
      if (!s.encontrado) {
        setSigeMeta({ sige_persona_id: null, sige_synced_at: null })
        return
      }
      if (s.nombre) setValue('nombre', s.nombre)
      if (s.email) setValue('email', s.email)
      if (s.contacto) setValue('contacto', s.contacto)
      if (s.telefono) setValue('telefono', s.telefono)
      if (s.tipo_persona) setValue('tipo_persona', s.tipo_persona)
      if (s.inscripcion) setValue('inscripcion', s.inscripcion)
      if (s.contabilidad) setValue('contabilidad', s.contabilidad)
      setSigeMeta({ sige_persona_id: s.sige_persona_id, sige_synced_at: ahoraComoDatetimeMysql() })
    },
  })
  const buscarEnSige = () => {
    const cuit = getValues('cuit') ?? ''
    sige.buscar(cuit)
  }

  const padron = useCuitLookup(sugerenciaPadron, {
    notFoundMessage: 'No se pudo consultar el padrón (¿certificado de ARCA?).',
    onSuccess: (s) => {
      if (s.nombre) setValue('nombre', s.nombre)
      if (s.domicilio) setValue('domicilio', s.domicilio)
      if (s.localidad) setValue('localidad', s.localidad)
      if (s.provincia_id != null) setValue('provincia_id', String(s.provincia_id))
      if (s.inicio_actividad) setValue('fecha_inicio_actividades', s.inicio_actividad)
      const act1 = actividadIdPorCodigo(s.actividad1_id, actividades)
      if (act1) setValue('actividad1_id', act1)
      const act2 = actividadIdPorCodigo(s.actividad2_id, actividades)
      if (act2) setValue('actividad2_id', act2)
      const cond = matchCondicion(s.condicion_iva, condiciones)
      if (cond) setValue('condicion_iva_id', cond)
    },
  })
  const buscarEnPadron = () => {
    const cuit = getValues('cuit') ?? ''
    padron.buscar(cuit)
  }

  // "CUIT único" (informe del cliente 10/08/2026, pedido 3): si ese CUIT ya está en el padrón de
  // sujetos (cliente/proveedor de alguna empresa del estudio), se ofrece reusar sus datos en vez
  // de tipearlos de nuevo al dar de alta la empresa (contribuyente).
  const padronSujetos = useCuitLookup(buscarSujetoPorCuit, {
    notFoundMessage: 'No se pudo consultar el padrón de sujetos.',
    onSuccess: (s) => {
      if (!s.encontrado) return
      if (s.nombre) setValue('nombre', s.nombre)
      if (s.domicilio) setValue('domicilio', s.domicilio)
      if (s.localidad) setValue('localidad', s.localidad)
      if (s.telefono) setValue('telefono', s.telefono)
      if (s.provincia_id != null) setValue('provincia_id', String(s.provincia_id))
      if (s.condicion_iva_id != null) setValue('condicion_iva_id', String(s.condicion_iva_id))
    },
  })
  const buscarEnPadronSujetos = () => padronSujetos.buscar(getValues('cuit') ?? '')

  useEffect(() => {
    if (visible) {
      catalogoAplicado.current = false
      padron.reset()
      sige.reset()
      padronSujetos.reset()
      setSigeMeta({
        sige_persona_id: empresa?.sige_persona_id ?? null,
        sige_synced_at: empresa?.sige_synced_at ?? null,
      })
      reset({
        nombre: empresa?.nombre ?? '',
        cuit: empresa?.cuit ?? '',
        email: empresa?.email ?? '',
        domicilio: empresa?.domicilio ?? '',
        localidad: empresa?.localidad ?? '',
        provincia_id: empresa?.provincia_id != null ? String(empresa.provincia_id) : '',
        telefono: empresa?.telefono ?? '',
        condicion_iva_id: empresa?.condicion_iva_id != null ? String(empresa.condicion_iva_id) : '',
        ingresos_brutos: empresa?.ingresos_brutos ?? '',
        fecha_inicio_actividades: empresa?.fecha_inicio_actividades ?? '',
        actividad1_id: empresa?.actividad1_id != null ? String(empresa.actividad1_id) : '',
        actividad2_id: empresa?.actividad2_id != null ? String(empresa.actividad2_id) : '',
        contacto: empresa?.contacto ?? '',
        tipo_persona: empresa?.tipo_persona ?? '',
        inscripcion: empresa?.inscripcion ?? '',
        contabilidad: empresa?.contabilidad ?? '',
      })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, empresa, reset])

  // Reaplica los valores de selects (condición/provincia) cuando cargan sus catálogos, una vez por apertura.
  useEffect(() => {
    if (visible && condiciones && provincias && !catalogoAplicado.current) {
      if (empresa?.condicion_iva_id != null) setValue('condicion_iva_id', String(empresa.condicion_iva_id))
      if (empresa?.provincia_id != null) setValue('provincia_id', String(empresa.provincia_id))
      catalogoAplicado.current = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, condiciones, provincias, empresa])

  const submit = (v: FormValues) =>
    onSubmit({
      ...v,
      condicion_iva_id: v.condicion_iva_id ? Number(v.condicion_iva_id) : null,
      provincia_id: v.provincia_id ? Number(v.provincia_id) : null,
      fecha_inicio_actividades: v.fecha_inicio_actividades || null,
      actividad1_id: v.actividad1_id ? Number(v.actividad1_id) : null,
      actividad2_id: v.actividad2_id ? Number(v.actividad2_id) : null,
      sige_persona_id: sigeMeta.sige_persona_id,
      sige_synced_at: sigeMeta.sige_synced_at,
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
            <div className="d-flex gap-2 flex-wrap">
              <CFormInput
                id="cuit"
                placeholder="Ej. 30-12345678-9"
                style={{ flex: '1 1 200px' }}
                {...register('cuit')}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    buscarEnSige()
                  }
                }}
              />
              <CButton
                type="button"
                color="primary"
                disabled={sige.isPending}
                onClick={buscarEnSige}
                title="Traer los datos del SIGE (sistemaCuarto) y completar el formulario"
                style={{ whiteSpace: 'nowrap' }}
              >
                {sige.isPending ? <CSpinner size="sm" /> : 'Buscar en SIGE'}
              </CButton>
              <CButton
                type="button"
                color="info"
                variant="outline"
                disabled={padron.isPending}
                onClick={buscarEnPadron}
                title="Traer los datos de ARCA (padrón): domicilio, provincia, condición de IVA y actividad"
                style={{ whiteSpace: 'nowrap' }}
              >
                {padron.isPending ? <CSpinner size="sm" /> : 'Obtener datos de AFIP'}
              </CButton>
              <CButton
                type="button"
                color="secondary"
                variant="outline"
                disabled={padronSujetos.isPending}
                onClick={buscarEnPadronSujetos}
                title="Ver si este CUIT ya está cargado como cliente/proveedor en el padrón, para no tipearlo de nuevo"
                style={{ whiteSpace: 'nowrap' }}
              >
                {padronSujetos.isPending ? <CSpinner size="sm" /> : 'Buscar en Padrón'}
              </CButton>
            </div>
            {sige.error && <div className="text-danger small mt-1">{sige.error}</div>}
            {sige.isSuccess && !sige.error && sige.data?.encontrado === false && (
              <div className="text-muted small mt-1">
                No está cargado en el SIGE todavía — podés seguir completando el alta a mano.
              </div>
            )}
            {sige.isSuccess && !sige.error && sige.data?.encontrado === true && (
              <div className="text-success small mt-1">
                Datos traídos del SIGE. Para domicilio, provincia, condición de IVA y actividad, usá
                &quot;Obtener datos de AFIP&quot;.
              </div>
            )}
            {padron.error && <div className="text-danger small mt-1">{padron.error}</div>}
            {padron.isSuccess && !padron.error && (
              <div className="text-success small mt-1">
                Datos traídos del padrón de ARCA. El teléfono no lo publica ARCA: cargalo a mano.
              </div>
            )}
            {padronSujetos.error && <div className="text-danger small mt-1">{padronSujetos.error}</div>}
            {padronSujetos.isSuccess && !padronSujetos.error && padronSujetos.data?.encontrado === false && (
              <div className="text-muted small mt-1">No está cargado como cliente/proveedor en el padrón.</div>
            )}
            {padronSujetos.isSuccess && !padronSujetos.error && padronSujetos.data?.encontrado === true && (
              <div className="text-success small mt-1">
                Ya está en el padrón como cliente/proveedor ({padronSujetos.data.nombre}) — datos traídos, no
                hizo falta tipearlos de nuevo.
              </div>
            )}
          </div>

          <div className="mb-3">
            <CFormLabel htmlFor="nombre">Nombre / Razón social *</CFormLabel>
            <CFormInput id="nombre" invalid={!!errors.nombre} {...register('nombre')} />
            {errors.nombre && <div className="text-danger small mt-1">{errors.nombre.message}</div>}
          </div>

          <div className="mb-3">
            <CFormLabel htmlFor="domicilio">Dirección</CFormLabel>
            <CFormInput id="domicilio" {...register('domicilio')} />
          </div>

          <div className="row">
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="localidad">Localidad</CFormLabel>
              <CFormInput id="localidad" {...register('localidad')} />
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
            <div className="col-md-4 mb-3">
              <CFormLabel htmlFor="telefono">Teléfono</CFormLabel>
              <CFormInput id="telefono" {...register('telefono')} />
            </div>
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

          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="contacto">Contacto</CFormLabel>
              <CFormInput id="contacto" placeholder="Nombre de la persona de contacto" {...register('contacto')} />
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="tipo_persona">Tipo de persona</CFormLabel>
              <CFormSelect id="tipo_persona" {...register('tipo_persona')}>
                <option value="">—</option>
                <option value="fisica">Física</option>
                <option value="juridica">Jurídica</option>
              </CFormSelect>
            </div>
          </div>

          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="inscripcion">Inscripción</CFormLabel>
              <CFormInput id="inscripcion" {...register('inscripcion')} />
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="contabilidad">Contabilidad</CFormLabel>
              <CFormInput id="contabilidad" {...register('contabilidad')} />
            </div>
          </div>

          <div className="mb-3">
            <CFormLabel htmlFor="actividad1">Actividad principal</CFormLabel>
            <input type="hidden" {...register('actividad1_id')} />
            <ActividadSelect
              id="actividad1"
              actividades={actividades}
              value={watch('actividad1_id') ?? ''}
              onChange={(v) => setValue('actividad1_id', v, { shouldDirty: true })}
            />
          </div>

          <div className="mb-3">
            <CFormLabel htmlFor="actividad2">Actividad secundaria</CFormLabel>
            <input type="hidden" {...register('actividad2_id')} />
            <ActividadSelect
              id="actividad2"
              actividades={actividades}
              value={watch('actividad2_id') ?? ''}
              onChange={(v) => setValue('actividad2_id', v, { shouldDirty: true })}
            />
          </div>

          <div className="row">
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="fecha_inicio_actividades">Inicio de actividad</CFormLabel>
              <CFormInput id="fecha_inicio_actividades" type="date" {...register('fecha_inicio_actividades')} />
            </div>
            <div className="col-md-6 mb-3">
              <CFormLabel htmlFor="ingresos_brutos">Ingresos Brutos</CFormLabel>
              <CFormInput id="ingresos_brutos" {...register('ingresos_brutos')} />
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
