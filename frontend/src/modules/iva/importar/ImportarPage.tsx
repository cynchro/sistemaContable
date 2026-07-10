import { useMemo, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CButton,
  CButtonGroup,
  CFormCheck,
  CFormInput,
  CFormLabel,
  CFormSelect,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CAlert,
  CBadge,
} from '@coreui/react'
import { parseCsv, normNumber, normDate, type CsvParsed } from './csv'
import { importVentas, importCompras, type ImportResultado } from '../../../api/importar'
import { listTiposRetencionAbm } from '../../../api/tiposRetencion'
import { listPeriodos } from '../../../api/periodos'
import type { VentaInput, DiscriminacionInput, PercepcionInput } from '../../../api/ventas'
import type { CompraInput } from '../../../api/compras'

type Destino = 'ventas' | 'compras'

interface Campo {
  key: string
  label: string
  required?: boolean
}

/** Campos destino mapeables. No incluye los que referencian catálogos por id (tipo de
 * comprobante, condición, etc.): se dejan en null y se completan editando luego. */
const CAMPOS: Campo[] = [
  { key: 'fecha', label: 'Fecha', required: true },
  { key: 'letra', label: 'Letra' },
  { key: 'punto_venta', label: 'Punto de venta' },
  { key: 'numero', label: 'Número' },
  { key: 'cuit', label: 'CUIT / Doc.' },
  { key: 'nombre', label: 'Nombre / Razón social' },
  { key: 'neto_gravado', label: 'Neto gravado', required: true },
  { key: 'iva_alicuota', label: 'Alícuota % (def. 21)' },
  { key: 'iva_importe', label: 'IVA (importe, opcional)' },
  { key: 'total_informado', label: 'Importe Total (informado)' },
  { key: 'neto_no_grav', label: 'Neto no gravado' },
  { key: 'exento', label: 'Exento' },
  { key: 'imp_interno', label: 'Imp. internos' },
  { key: 'campo_auxiliar', label: 'Campo auxiliar' },
]

/** Heurística para pre-mapear columnas por nombre de encabezado (AFIP "Mis Comprobantes"). */
const PISTAS: Record<string, RegExp> = {
  fecha: /fecha/i,
  letra: /letra/i,
  punto_venta: /punto/i,
  numero: /n[uú]mero/i,
  cuit: /cuit|nro\.?\s*doc|documento/i,
  nombre: /denomin|nombre|raz[oó]n/i,
  neto_gravado: /neto\s*grav/i,
  iva_alicuota: /al[ií]cuota/i,
  iva_importe: /^\s*iva\s*$|importe.*iva/i,
  total_informado: /importe\s*total|^\s*total\s*$/i,
  neto_no_grav: /no\s*grav/i,
  exento: /exent/i,
  imp_interno: /interno/i,
}

type Mapping = Record<string, string> // key destino → índice de columna (string) o ''

/** Alícuotas de IVA vigentes: se usan para "encajar" la que se deriva de IVA/neto. */
const ALICUOTAS = [0, 2.5, 5, 10.5, 21, 27]

/** Deriva la alícuota desde el importe de IVA y el neto (iva = neto × alíc./100) y la
 * encaja a la alícuota vigente más cercana. Devuelve '' si no se puede derivar. */
function derivarAlicuota(neto: string | undefined, iva: string | undefined): string {
  const n = Number(neto)
  const i = Number(iva)
  if (!Number.isFinite(n) || !Number.isFinite(i) || n === 0 || i === 0) return ''
  const pct = (i / n) * 100
  let best = ALICUOTAS[0]
  for (const a of ALICUOTAS) {
    if (Math.abs(a - pct) < Math.abs(best - pct)) best = a
  }
  // Solo la aceptamos si está razonablemente cerca (evita ruido por redondeos raros).
  return Math.abs(best - pct) <= 1.5 ? String(best) : ''
}

/** Asocia una columna del CSV (importe) a un tipo de percepción/retención del catálogo. */
interface PercepMapping {
  col: string // índice de columna (string) o ''
  tipoId: string // id de tipo_retencion (string) o ''
}

function autoMap(headers: string[]): Mapping {
  const m: Mapping = {}
  for (const c of CAMPOS) {
    const re = PISTAS[c.key]
    if (!re) {
      m[c.key] = ''
      continue
    }
    const idx = headers.findIndex((h) => re.test(h))
    m[c.key] = idx >= 0 ? String(idx) : ''
  }
  return m
}

function buildComprobante(
  row: string[],
  map: Mapping,
  destino: Destino,
  percepMappings: PercepMapping[],
): VentaInput | CompraInput {
  const get = (key: string): string | undefined => {
    const idx = map[key]
    return idx === '' || idx == null ? undefined : row[Number(idx)]
  }

  const neto = normNumber(get('neto_gravado')) || '0'
  const ivaImporte = normNumber(get('iva_importe'))
  // Alícuota: la mapeada; si no, la derivada de IVA/neto; y como último recurso, 21%.
  const alicuota = normNumber(get('iva_alicuota')) || derivarAlicuota(neto, ivaImporte) || '21'
  const disc: DiscriminacionInput = {
    neto_gravado: neto,
    iva_alicuota: alicuota,
  }
  // Override del IVA (regla del asterisco): si el CSV trae el importe de IVA (p. ej. el de
  // AFIP) lo respetamos; si no, lo calcula el motor desde neto × alícuota.
  if (ivaImporte) disc.iva_importe = ivaImporte

  // Percepciones/retenciones: cada mapeo lee su columna; se agrega solo si trae un importe ≠ 0.
  const percepciones: PercepcionInput[] = []
  for (const pm of percepMappings) {
    if (pm.col === '' || pm.tipoId === '') continue
    const val = normNumber(row[Number(pm.col)])
    if (val && Number(val) !== 0) {
      percepciones.push({ tipo_retencion_id: Number(pm.tipoId), importe: val })
    }
  }

  const base = {
    fecha: normDate(get('fecha')),
    letra: get('letra') || null,
    punto_venta: get('punto_venta') || null,
    numero: get('numero') || null,
    cuit: get('cuit') || null,
    neto_no_grav: normNumber(get('neto_no_grav')) || null,
    exento: normNumber(get('exento')) || null,
    imp_interno: normNumber(get('imp_interno')) || null,
    campo_auxiliar: get('campo_auxiliar') || null,
    // Total informado (el del comprobante/ARCA): si viene, el Libro IVA Digital lo usa en vez
    // del derivado (neto+iva), para paridad byte a byte con lo que ya presenta el estudio.
    total_informado: normNumber(get('total_informado')) || null,
    discriminaciones: [disc],
    ...(percepciones.length > 0 ? { percepciones } : {}),
  }
  const nombre = get('nombre') || null
  return destino === 'ventas'
    ? ({ ...base, cliente_nombre: nombre } as VentaInput)
    : ({ ...base, proveedor_nombre: nombre } as CompraInput)
}

/** Un problema detectado en una fila del preview. `error` bloquea la importación de esa
 * fila; `warning` la deja pasar pero avisa (el backend igual la valida). */
interface Issue {
  nivel: 'error' | 'warning'
  texto: string
}

/** Valida un comprobante ya construido contra reglas locales (sin ir al backend), para
 * marcar en el preview lo que va a fallar o merece revisión antes de importar. */
function validarComprobante(
  c: VentaInput | CompraInput,
  destino: Destino,
  periodo: { fecha_ini: string | null; fecha_fin: string | null } | undefined,
): Issue[] {
  const issues: Issue[] = []
  const nombre = (c as unknown as Record<string, unknown>)[
    destino === 'ventas' ? 'cliente_nombre' : 'proveedor_nombre'
  ] as string | null

  if (!c.fecha) {
    issues.push({ nivel: 'error', texto: 'Falta la fecha' })
  } else if (periodo?.fecha_ini && periodo.fecha_fin) {
    // YYYY-MM-DD compara lexicográfico.
    if (c.fecha < periodo.fecha_ini || c.fecha > periodo.fecha_fin) {
      issues.push({ nivel: 'warning', texto: 'Fecha fuera del período' })
    }
  }

  const d = c.discriminaciones[0]
  const neto = Number(d?.neto_gravado ?? 0)
  const noGrav = Number(c.neto_no_grav ?? 0)
  const exento = Number(c.exento ?? 0)
  if (neto === 0 && noGrav === 0 && exento === 0) {
    issues.push({ nivel: 'error', texto: 'Sin importes (neto, no gravado ni exento)' })
  }

  // CUIT/documento: si viene, avisamos cuando no parece un CUIT de 11 dígitos.
  if (c.cuit) {
    const soloDig = c.cuit.replace(/\D/g, '')
    if (soloDig.length !== 11) {
      issues.push({ nivel: 'warning', texto: 'CUIT/Doc. no tiene 11 dígitos' })
    }
  }

  if (!nombre) {
    issues.push({ nivel: 'warning', texto: 'Sin nombre / razón social' })
  }

  return issues
}

export default function ImportarPage() {
  const { empresaId, periodoId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(periodoId)

  const [destino, setDestino] = useState<Destino>('compras')
  const [fileName, setFileName] = useState('')
  const [parsed, setParsed] = useState<CsvParsed | null>(null)
  const [mapping, setMapping] = useState<Mapping>({})
  const [percepMappings, setPercepMappings] = useState<PercepMapping[]>([])
  const [parseError, setParseError] = useState<string | null>(null)
  const [result, setResult] = useState<ImportResultado | null>(null)
  const [omitirConError, setOmitirConError] = useState(true)

  const { data: tipos = [] } = useQuery({
    queryKey: ['tipos-retencion-abm'],
    queryFn: listTiposRetencionAbm,
  })

  const { data: periodos = [] } = useQuery({
    queryKey: ['periodos', eId],
    queryFn: () => listPeriodos(eId),
  })
  const periodo = periodos.find((p) => p.id === pId)
  const periodoCerrado = periodo?.cerrado === 'S'

  const onFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    setResult(null)
    setParseError(null)
    if (!file) return
    setFileName(file.name)
    try {
      const text = await file.text()
      const p = parseCsv(text)
      if (p.headers.length === 0) {
        setParseError('El archivo no tiene encabezados reconocibles.')
        setParsed(null)
        return
      }
      setParsed(p)
      setMapping(autoMap(p.headers))
      setPercepMappings([])
    } catch {
      setParseError('No se pudo leer el archivo.')
      setParsed(null)
    }
  }

  const filas = useMemo(() => {
    if (!parsed) return []
    return parsed.rows.map((r) => {
      const comp = buildComprobante(r, mapping, destino, percepMappings)
      return { comp, issues: validarComprobante(comp, destino, periodo) }
    })
  }, [parsed, mapping, destino, percepMappings, periodo])

  const conError = filas.filter((f) => f.issues.some((i) => i.nivel === 'error')).length
  const conAviso = filas.filter(
    (f) => !f.issues.some((i) => i.nivel === 'error') && f.issues.length > 0,
  ).length
  // Lo que realmente se envía: si "omitir con error" está activo, se filtran esas filas.
  const aImportar = useMemo(
    () => (omitirConError ? filas.filter((f) => !f.issues.some((i) => i.nivel === 'error')) : filas).map((f) => f.comp),
    [filas, omitirConError],
  )

  const nombreCampo = destino === 'ventas' ? 'cliente_nombre' : 'proveedor_nombre'

  const importM = useMutation({
    mutationFn: () =>
      destino === 'ventas'
        ? importVentas(eId, pId, aImportar as VentaInput[])
        : importCompras(eId, pId, aImportar as CompraInput[]),
    onSuccess: (r) => setResult(r),
  })

  const faltaObligatorio = CAMPOS.filter((c) => c.required && !mapping[c.key])
  const conceptoPercep = destino === 'ventas' ? 'Percepciones' : 'Retenciones / percepciones'

  return (
    <CCard>
      <CCardHeader className="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <Link to="/empresas" className="text-decoration-none small">
            ← Empresas
          </Link>
          <strong className="ms-2">Importar comprobantes</strong>
        </div>
        <CButtonGroup size="sm">
          <CButton
            color="primary"
            variant={destino === 'compras' ? undefined : 'outline'}
            onClick={() => {
              setDestino('compras')
              setResult(null)
            }}
          >
            Compras (recibidos)
          </CButton>
          <CButton
            color="primary"
            variant={destino === 'ventas' ? undefined : 'outline'}
            onClick={() => {
              setDestino('ventas')
              setResult(null)
            }}
          >
            Ventas (emitidos)
          </CButton>
        </CButtonGroup>
      </CCardHeader>
      <CCardBody>
        <p className="text-body-secondary">
          Subí un CSV (Mis Comprobantes de ARCA, o cualquier export). Se detectan las columnas y las
          mapeás a los campos del sistema; el total y el IVA los calcula el motor (salvo que mapees el
          importe de IVA). Si no mapeás la alícuota pero sí el IVA, se deduce de IVA/neto. Cada fila se
          valida antes de importar (fecha, importes, CUIT, período). Los comprobantes se crean en el
          período activo (empresa #{eId}, período #{pId}).
        </p>

        <div className="mb-3" style={{ maxWidth: 420 }}>
          <CFormLabel htmlFor="csv">Archivo CSV</CFormLabel>
          <CFormInput id="csv" type="file" accept=".csv,text/csv,text/plain" onChange={onFile} />
          {fileName && <div className="small text-body-secondary mt-1">{fileName}</div>}
        </div>

        {parseError && <CAlert color="danger">{parseError}</CAlert>}

        {parsed && (
          <>
            <div className="mb-2">
              <strong>Mapeo de columnas</strong>{' '}
              <span className="text-body-secondary small">
                ({parsed.rows.length} filas · separador «{parsed.delimiter === '\t' ? 'TAB' : parsed.delimiter}»)
              </span>
            </div>
            <div className="row g-2 mb-3">
              {CAMPOS.map((c) => (
                <div className="col-md-3" key={c.key}>
                  <CFormLabel className="small mb-1">
                    {c.label} {c.required && <span className="text-danger">*</span>}
                  </CFormLabel>
                  <CFormSelect
                    size="sm"
                    value={mapping[c.key] ?? ''}
                    onChange={(e) => setMapping((m) => ({ ...m, [c.key]: e.target.value }))}
                  >
                    <option value="">—</option>
                    {parsed.headers.map((h, i) => (
                      <option key={i} value={i}>
                        {h || `Columna ${i + 1}`}
                      </option>
                    ))}
                  </CFormSelect>
                </div>
              ))}
            </div>

            <div className="mb-2 d-flex align-items-center gap-2">
              <strong>{conceptoPercep}</strong>
              <span className="text-body-secondary small">
                (asociá una columna de importe a un tipo del catálogo; integra el total)
              </span>
              <CButton
                size="sm"
                color="secondary"
                variant="outline"
                className="ms-auto"
                disabled={tipos.length === 0}
                onClick={() => setPercepMappings((p) => [...p, { col: '', tipoId: '' }])}
              >
                + Agregar {destino === 'ventas' ? 'percepción' : 'retención'}
              </CButton>
            </div>
            {tipos.length === 0 && (
              <div className="small text-body-secondary mb-2">
                No hay tipos de retención/percepción cargados. Definilos en Utilidades → Tipos de retención.
              </div>
            )}
            {percepMappings.length > 0 && (
              <div className="row g-2 mb-3">
                {percepMappings.map((pm, idx) => (
                  <div className="col-12 d-flex gap-2 align-items-end" key={idx}>
                    <div style={{ flex: 1, maxWidth: 260 }}>
                      <CFormLabel className="small mb-1">Columna (importe)</CFormLabel>
                      <CFormSelect
                        size="sm"
                        value={pm.col}
                        onChange={(e) =>
                          setPercepMappings((p) => p.map((x, i) => (i === idx ? { ...x, col: e.target.value } : x)))
                        }
                      >
                        <option value="">—</option>
                        {parsed.headers.map((h, i) => (
                          <option key={i} value={i}>
                            {h || `Columna ${i + 1}`}
                          </option>
                        ))}
                      </CFormSelect>
                    </div>
                    <div style={{ flex: 1, maxWidth: 320 }}>
                      <CFormLabel className="small mb-1">Tipo</CFormLabel>
                      <CFormSelect
                        size="sm"
                        value={pm.tipoId}
                        onChange={(e) =>
                          setPercepMappings((p) => p.map((x, i) => (i === idx ? { ...x, tipoId: e.target.value } : x)))
                        }
                      >
                        <option value="">—</option>
                        {tipos.map((t) => (
                          <option key={t.id} value={t.id}>
                            {t.nombre}
                            {t.tenant_id === null ? ' (estándar)' : ''}
                          </option>
                        ))}
                      </CFormSelect>
                    </div>
                    <CButton
                      size="sm"
                      color="danger"
                      variant="outline"
                      onClick={() => setPercepMappings((p) => p.filter((_, i) => i !== idx))}
                    >
                      Quitar
                    </CButton>
                  </div>
                ))}
              </div>
            )}

            <div className="mb-2">
              <strong>Vista previa</strong> <span className="text-body-secondary small">(primeras 8 filas)</span>
            </div>
            <CTable small bordered responsive align="middle" className="ledger mb-3">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell style={{ width: 40 }}>#</CTableHeaderCell>
                  <CTableHeaderCell>Fecha</CTableHeaderCell>
                  <CTableHeaderCell>Comprobante</CTableHeaderCell>
                  <CTableHeaderCell>Nombre</CTableHeaderCell>
                  <CTableHeaderCell>CUIT</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Neto gravado</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Alíc. %</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">IVA</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Perc.</CTableHeaderCell>
                  <CTableHeaderCell>Estado</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {filas.slice(0, 8).map(({ comp: c, issues }, i) => {
                  const d = c.discriminaciones[0]
                  const nombre = (c as unknown as Record<string, unknown>)[nombreCampo] as string | null
                  const perc = c.percepciones ?? []
                  const tieneError = issues.some((x) => x.nivel === 'error')
                  return (
                    <CTableRow key={i} color={tieneError ? 'danger' : issues.length > 0 ? 'warning' : undefined}>
                      <CTableDataCell className="text-body-secondary">{i + 1}</CTableDataCell>
                      <CTableDataCell>{c.fecha || <span className="text-danger">—</span>}</CTableDataCell>
                      <CTableDataCell>
                        {`${c.letra ?? ''} ${c.punto_venta ?? ''}-${c.numero ?? ''}`.trim()}
                      </CTableDataCell>
                      <CTableDataCell>{nombre ?? '—'}</CTableDataCell>
                      <CTableDataCell>{c.cuit ?? '—'}</CTableDataCell>
                      <CTableDataCell className="text-end">{d.neto_gravado}</CTableDataCell>
                      <CTableDataCell className="text-end">{d.iva_alicuota}</CTableDataCell>
                      <CTableDataCell className="text-end">{d.iva_importe ?? '—'}</CTableDataCell>
                      <CTableDataCell className="text-end">
                        {perc.length > 0 ? perc.reduce((s, p) => s + Number(p.importe ?? 0), 0) : '—'}
                      </CTableDataCell>
                      <CTableDataCell>
                        {issues.length === 0 ? (
                          <CBadge color="success">OK</CBadge>
                        ) : (
                          <span title={issues.map((x) => x.texto).join(' · ')}>
                            {issues.map((x, k) => (
                              <CBadge key={k} color={x.nivel === 'error' ? 'danger' : 'warning'} className="me-1">
                                {x.texto}
                              </CBadge>
                            ))}
                          </span>
                        )}
                      </CTableDataCell>
                    </CTableRow>
                  )
                })}
              </CTableBody>
            </CTable>

            {(conError > 0 || conAviso > 0) && (
              <div className="d-flex flex-wrap gap-3 align-items-center mb-2 small">
                <span>
                  <CBadge color="success" className="me-1">
                    {filas.length - conError - conAviso}
                  </CBadge>
                  válidas
                </span>
                {conAviso > 0 && (
                  <span>
                    <CBadge color="warning" className="me-1">
                      {conAviso}
                    </CBadge>
                    con aviso (se importan igual)
                  </span>
                )}
                {conError > 0 && (
                  <span>
                    <CBadge color="danger" className="me-1">
                      {conError}
                    </CBadge>
                    con error
                  </span>
                )}
              </div>
            )}

            {conError > 0 && (
              <div className="mb-3">
                <CFormCheck
                  id="omitir-error"
                  checked={omitirConError}
                  onChange={(e) => setOmitirConError(e.target.checked)}
                  label={`Omitir las ${conError} fila(s) con error (importar solo las válidas)`}
                />
              </div>
            )}

            {faltaObligatorio.length > 0 && (
              <CAlert color="warning">
                Mapeá los campos obligatorios: {faltaObligatorio.map((c) => c.label).join(', ')}.
              </CAlert>
            )}

            {periodoCerrado && (
              <CAlert color="danger">
                El período activo está <strong>cerrado</strong>: no se pueden importar comprobantes. Abrilo desde
                Períodos para poder cargar.
              </CAlert>
            )}

            <CButton
              color="primary"
              disabled={
                importM.isPending ||
                faltaObligatorio.length > 0 ||
                aImportar.length === 0 ||
                periodoCerrado
              }
              onClick={() => importM.mutate()}
            >
              {importM.isPending
                ? 'Importando…'
                : `Importar ${aImportar.length} comprobante(s) a ${destino === 'ventas' ? 'Ventas' : 'Compras'}`}
            </CButton>
            {importM.isError && <CAlert color="danger" className="mt-3">No se pudo importar.</CAlert>}
          </>
        )}

        {result && (
          <div className="mt-4">
            <CAlert color={result.errores.length === 0 ? 'success' : 'warning'}>
              <strong>{result.creados}</strong> de {result.total} comprobantes creados.
              {result.errores.length > 0 && <> {result.errores.length} con error.</>}
            </CAlert>
            {result.errores.length > 0 && (
              <CTable small bordered responsive align="middle" className="ledger">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell style={{ width: 90 }}>Fila</CTableHeaderCell>
                    <CTableHeaderCell>Error</CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {result.errores.map((e) => (
                    <CTableRow key={e.fila}>
                      <CTableDataCell>
                        <CBadge color="danger">#{e.fila + 1}</CBadge>
                      </CTableDataCell>
                      <CTableDataCell>{e.error}</CTableDataCell>
                    </CTableRow>
                  ))}
                </CTableBody>
              </CTable>
            )}
          </div>
        )}
      </CCardBody>
    </CCard>
  )
}
