import { useMemo, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CButton,
  CButtonGroup,
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
import type { VentaInput } from '../../../api/ventas'
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
  neto_no_grav: /no\s*grav/i,
  exento: /exent/i,
  imp_interno: /interno/i,
}

type Mapping = Record<string, string> // key destino → índice de columna (string) o ''

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

function buildComprobante(row: string[], map: Mapping, destino: Destino): VentaInput | CompraInput {
  const get = (key: string): string | undefined => {
    const idx = map[key]
    return idx === '' || idx == null ? undefined : row[Number(idx)]
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
    discriminaciones: [
      { neto_gravado: normNumber(get('neto_gravado')) || '0', iva_alicuota: normNumber(get('iva_alicuota')) || '21' },
    ],
  }
  const nombre = get('nombre') || null
  return destino === 'ventas'
    ? ({ ...base, cliente_nombre: nombre } as VentaInput)
    : ({ ...base, proveedor_nombre: nombre } as CompraInput)
}

export default function ImportarPage() {
  const { empresaId, periodoId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(periodoId)

  const [destino, setDestino] = useState<Destino>('compras')
  const [fileName, setFileName] = useState('')
  const [parsed, setParsed] = useState<CsvParsed | null>(null)
  const [mapping, setMapping] = useState<Mapping>({})
  const [parseError, setParseError] = useState<string | null>(null)
  const [result, setResult] = useState<ImportResultado | null>(null)

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
    } catch {
      setParseError('No se pudo leer el archivo.')
      setParsed(null)
    }
  }

  const comprobantes = useMemo(
    () => (parsed ? parsed.rows.map((r) => buildComprobante(r, mapping, destino)) : []),
    [parsed, mapping, destino],
  )

  const nombreCampo = destino === 'ventas' ? 'cliente_nombre' : 'proveedor_nombre'

  const importM = useMutation({
    mutationFn: () =>
      destino === 'ventas'
        ? importVentas(eId, pId, comprobantes as VentaInput[])
        : importCompras(eId, pId, comprobantes as CompraInput[]),
    onSuccess: (r) => setResult(r),
  })

  const faltaObligatorio = CAMPOS.filter((c) => c.required && !mapping[c.key])

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
          mapeás a los campos del sistema; el total y el IVA los calcula el motor. Los comprobantes se
          crean en el período activo (empresa #{eId}, período #{pId}).
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

            <div className="mb-2">
              <strong>Vista previa</strong> <span className="text-body-secondary small">(primeras 6 filas)</span>
            </div>
            <CTable small bordered responsive align="middle" className="ledger mb-3">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Fecha</CTableHeaderCell>
                  <CTableHeaderCell>Comprobante</CTableHeaderCell>
                  <CTableHeaderCell>Nombre</CTableHeaderCell>
                  <CTableHeaderCell>CUIT</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Neto gravado</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Alíc. %</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {comprobantes.slice(0, 6).map((c, i) => {
                  const d = c.discriminaciones[0]
                  const nombre = (c as unknown as Record<string, unknown>)[nombreCampo] as string | null
                  return (
                    <CTableRow key={i}>
                      <CTableDataCell>{c.fecha || <span className="text-danger">—</span>}</CTableDataCell>
                      <CTableDataCell>
                        {`${c.letra ?? ''} ${c.punto_venta ?? ''}-${c.numero ?? ''}`.trim()}
                      </CTableDataCell>
                      <CTableDataCell>{nombre ?? '—'}</CTableDataCell>
                      <CTableDataCell>{c.cuit ?? '—'}</CTableDataCell>
                      <CTableDataCell className="text-end">{d.neto_gravado}</CTableDataCell>
                      <CTableDataCell className="text-end">{d.iva_alicuota}</CTableDataCell>
                    </CTableRow>
                  )
                })}
              </CTableBody>
            </CTable>

            {faltaObligatorio.length > 0 && (
              <CAlert color="warning">
                Mapeá los campos obligatorios: {faltaObligatorio.map((c) => c.label).join(', ')}.
              </CAlert>
            )}

            <CButton
              color="primary"
              disabled={importM.isPending || faltaObligatorio.length > 0 || comprobantes.length === 0}
              onClick={() => importM.mutate()}
            >
              {importM.isPending
                ? 'Importando…'
                : `Importar ${comprobantes.length} comprobante(s) a ${destino === 'ventas' ? 'Ventas' : 'Compras'}`}
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
