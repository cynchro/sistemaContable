const PASOS_IVA = ['Empresas', 'Períodos', 'Ventas / Compras', 'Libro IVA / DDJJ', 'Descargas']

const APOYO_IVA = [
  'Padrón único',
  'Cuentas',
  'Actividades',
  'Alertas',
  'Auditoría ARCA',
  'Importar comprobantes',
  'Factura electrónica (AFIP)',
]

/**
 * Diagrama estático del flujo del sistema (Manuales › Navegación), complemento visual del
 * tour "Mapa del sistema" (usePageTour → tourNavegacion) y del texto de esta sección. Usa
 * clases propias definidas en index.css con variables de CoreUI, así se adapta solo a
 * claro/oscuro sin código de tema a mano.
 */
export default function FlujoSistemaDiagrama() {
  return (
    <div className="mb-4">
      <div className="flow-main mb-2">
        {PASOS_IVA.map((paso, i) => (
          <div key={paso} className="d-flex align-items-center gap-2">
            <div className="flow-step bg-body-tertiary">{paso}</div>
            {i < PASOS_IVA.length - 1 && <span className="flow-arrow">→</span>}
          </div>
        ))}
      </div>
      <div className="text-body-secondary small mb-2">
        Circuito principal de IVA. Herramientas de apoyo (se usan cuando hacen falta, no en cada período):
      </div>
      <div className="flow-support mb-3">
        {APOYO_IVA.map((a) => (
          <span key={a} className="flow-chip">
            {a}
          </span>
        ))}
      </div>

      <div className="flow-areas">
        <div className="flow-area bg-body-tertiary">
          <strong>Estudio</strong>
          <span className="text-body-secondary">Sueldos · Vencimientos y tareas · Honorarios</span>
        </div>
        <div className="flow-area bg-body-tertiary">
          <strong>Administración</strong>
          <span className="text-body-secondary">Roles y permisos · Utilidades (catálogos, conceptos, auditoría)</span>
        </div>
      </div>
      <div className="text-body-secondary small mt-2">
        Estudio y Administración son áreas independientes: no dependen de la empresa ni el período activos.
      </div>
    </div>
  )
}
