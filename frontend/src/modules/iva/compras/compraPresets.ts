/**
 * Presets de carga para los comprobantes de compra "manuales" que el estudio agrega por
 * fuera de ARCA (respuesta R5 del contador, 07/07). Cada preset pre-arma el modal con el
 * tipo de comprobante, la letra, el concepto (DJ IVA) y las líneas de alícuota típicas,
 * más una ayuda con la convención de punto de venta / número que usa el estudio.
 *
 * El `tipoCodigo` se resuelve contra el catálogo `tipos-comprobante` dentro del modal
 * (código → id); si no se encuentra, queda sin tipo (no rompe).
 */
export interface CompraPreset {
  id: string
  label: string
  /** Código del catálogo tipos-comprobante (TF = Ticket Factura A cód. 81, FA, LA…). */
  tipoCodigo?: string
  letra?: string
  /** Concepto DJ IVA: 1 bienes · 2 locaciones · 3 servicios · 4 bienes de uso. */
  conceptoDj?: string
  /** Alícuotas (%) de las líneas de discriminación a pre-crear (neto vacío). */
  alicuotas: string[]
  hint: string
}

export const COMPRA_PRESETS: CompraPreset[] = [
  {
    id: 'resumen-bancario',
    label: 'Resumen bancario',
    tipoCodigo: 'FA',
    letra: 'A',
    conceptoDj: '3',
    alicuotas: ['21', '10.5'],
    hint:
      'Todos los movimientos del mes en 1 comprobante. Línea 21% = gastos/comisiones (mantenimiento, ' +
      'cheques, cajas de seguridad); línea 10,5% = intereses por giro en descubierto. Convención: ' +
      'Punto de venta = nº de banco (por CBU) · Número = MMAAXXXX (mes, año, últimos 4 del CBU).',
  },
  {
    id: 'ticket-combustible',
    label: 'Ticket combustible (cód. 81)',
    tipoCodigo: 'TF',
    letra: 'A',
    conceptoDj: '1',
    alicuotas: ['21'],
    hint:
      'Ticket Factura A de controlador fiscal. Cargá neto e IVA al 21%; el impuesto interno va aparte: ' +
      'poné el "Total del comprobante" y usá el botón "Imputar a Imp. interno" para el resto.',
  },
  {
    id: 'cuota-prestamo',
    label: 'Cuota de préstamo',
    tipoCodigo: 'FA',
    letra: 'A',
    conceptoDj: '3',
    alicuotas: ['10.5'],
    hint:
      '1 comprobante por cada cuota, según el mes de vencimiento. Cargá el interés de la cuota (IVA 10,5%). ' +
      'Convención: Punto de venta = 4 primeros dígitos del préstamo · Número = últimos 4 + nº de cuota.',
  },
  {
    id: 'liquidacion-tarjeta',
    label: 'Liquidación de tarjeta',
    tipoCodigo: 'FA',
    letra: 'A',
    conceptoDj: '3',
    alicuotas: ['21', '10.5'],
    hint:
      'Liquidación mensual del agente (Merchant Portal, Prisma, Naranja, Centrocard). Línea 21% = arancel ' +
      'del servicio; línea 10,5% = interés por financiación en cuotas. 1 asiento por el mes.',
  },
  {
    id: 'servicio-publico',
    label: 'Servicio público (luz/agua)',
    tipoCodigo: 'LA',
    letra: 'A',
    conceptoDj: '3',
    alicuotas: ['27'],
    hint: 'Liquidación de servicios públicos (luz, agua). IVA al 27%.',
  },
  {
    id: 'poliza-seguro',
    label: 'Póliza de seguro',
    tipoCodigo: 'FA',
    letra: 'A',
    conceptoDj: '3',
    alicuotas: ['21'],
    hint:
      'Póliza de seguro. Cargá neto e IVA; el impuesto interno (que la aseguradora no discrimina) sale por ' +
      'diferencia: poné el "Total del comprobante" y usá "Imputar a Imp. interno".',
  },
]
