<?php

namespace App\Modules\Iva\Export;

use App\Modules\Iva\Afip\Wsfe\CbteTipoResolver;
use App\Modules\Iva\Afip\Wsfe\AlicuotaIvaResolver;

/**
 * Genera los archivos de texto del Libro IVA Digital (Portal IVA) según el diseño de
 * registro de ARCA, validado byte a byte contra los TXT de ejemplo (`imagenes/`).
 * Lógica pura: recibe filas ya consultadas y devuelve el texto (registros separados
 * por CRLF, como los ejemplos reales).
 *
 * Implementa los 4 archivos de uso común:
 *   - VENTAS_CBTE      (266)   - VENTAS_ALICUOTAS   (62)
 *   - COMPRAS_CBTE     (325)   - COMPRAS_ALICUOTAS  (84)
 *
 * El tipo de comprobante se emite como CbteTipo de AFIP ({@see CbteTipoResolver},
 * codigo legacy + letra) y la alícuota como Id de AFIP ({@see AlicuotaIvaResolver}).
 */
final class LibroIvaDigitalWriter
{
    private const LEN_VENTAS_CBTE      = 266;
    private const LEN_VENTAS_ALICUOTAS = 62;
    private const LEN_COMPRAS_CBTE     = 325;
    private const LEN_COMPRAS_ALICUOTAS = 84;
    private const LEN_VENTAS_ANULADOS  = 44;

    private const EOL = "\r\n";

    /** @param list<array<string, mixed>> $rows */
    public function ventasCbte(array $rows): string
    {
        return $this->unir(array_map(fn (array $r): string => (new RegistroFijo())
            ->fecha($this->str($r, 'fecha'))
            ->entero($this->cbteTipo($r), 3)
            ->entero($this->str($r, 'punto_venta'), 5)
            ->entero($this->str($r, 'numero'), 20)
            ->entero($this->str($r, 'numero_fin'), 20)
            ->entero($this->str($r, 'doc_cod_afip'), 2)
            ->entero($this->str($r, 'cuit'), 20)
            ->texto($this->str($r, 'cliente_nombre'), 30)
            ->importe($r['total'] ?? 0)
            ->importe($r['neto_no_grav'] ?? 0)
            ->importe($r['perc_no_cat'] ?? 0)
            ->importe($r['exento'] ?? 0)
            ->importe($r['perc_nac'] ?? 0)
            ->importe($r['perc_iibb'] ?? 0)
            ->importe($r['perc_muni'] ?? 0)
            ->importe($r['imp_interno'] ?? 0)
            ->texto($this->str($r, 'moneda_codigo', 'PES'), 3)
            ->cambio($this->str($r, 'tipo_cambio'))
            ->entero($this->str($r, 'cant_alic'), 1)
            ->texto($this->str($r, 'codigo_operacion'), 1)
            ->importe($r['otros_trib'] ?? 0)
            ->fecha($this->str($r, 'fch_vto_pago'))
            ->build(self::LEN_VENTAS_CBTE), $rows));
    }

    /** @param list<array<string, mixed>> $rows */
    public function ventasAlicuotas(array $rows): string
    {
        return $this->unir(array_map(fn (array $r): string => (new RegistroFijo())
            ->entero($this->cbteTipo($r), 3)
            ->entero($this->str($r, 'punto_venta'), 5)
            ->entero($this->str($r, 'numero'), 20)
            ->importe($r['neto_gravado'] ?? 0)
            ->entero($this->alicuota($r), 4)
            ->importe($r['iva_importe'] ?? 0)
            ->build(self::LEN_VENTAS_ALICUOTAS), $rows));
    }

    /** @param list<array<string, mixed>> $rows */
    public function comprasCbte(array $rows): string
    {
        return $this->unir(array_map(fn (array $r): string => (new RegistroFijo())
            ->fecha($this->str($r, 'fecha'))
            ->entero($this->cbteTipo($r), 3)
            ->entero($this->str($r, 'punto_venta'), 5)
            ->entero($this->str($r, 'numero'), 20)
            ->texto('', 16) // Despacho de importación (blanco por defecto)
            ->entero('80', 2) // Código de documento del vendedor: CUIT
            ->entero($this->str($r, 'cuit'), 20)
            ->texto($this->str($r, 'proveedor_nombre'), 30)
            ->importe($r['total'] ?? 0)
            ->importe($r['neto_no_grav'] ?? 0)
            ->importe($r['exento'] ?? 0)
            ->importe($r['perc_iva'] ?? 0)
            ->importe($r['perc_nac'] ?? 0)
            ->importe($r['perc_iibb'] ?? 0)
            ->importe($r['perc_muni'] ?? 0)
            ->importe($r['imp_interno'] ?? 0)
            ->texto($this->str($r, 'moneda_codigo', 'PES'), 3)
            ->cambio($this->str($r, 'tipo_cambio'))
            ->entero($this->str($r, 'cant_alic'), 1)
            ->texto($this->str($r, 'codigo_operacion'), 1)
            ->importe($r['cf_computable'] ?? 0)
            ->importe($r['otros_trib'] ?? 0)
            ->entero('', 11) // CUIT emisor/corredor (sin corredor)
            ->texto('', 30) // Denominación del emisor/corredor
            ->importe(0) // IVA comisión
            ->build(self::LEN_COMPRAS_CBTE), $rows));
    }

    /** @param list<array<string, mixed>> $rows */
    public function comprasAlicuotas(array $rows): string
    {
        return $this->unir(array_map(fn (array $r): string => (new RegistroFijo())
            ->entero($this->cbteTipo($r), 3)
            ->entero($this->str($r, 'punto_venta'), 5)
            ->entero($this->str($r, 'numero'), 20)
            ->entero('80', 2) // Código de documento del vendedor: CUIT
            ->entero($this->str($r, 'cuit'), 20)
            ->importe($r['neto_gravado'] ?? 0)
            ->entero($this->alicuota($r), 4)
            ->importe($r['iva_importe'] ?? 0)
            ->build(self::LEN_COMPRAS_ALICUOTAS), $rows));
    }

    /**
     * Comprobantes de venta ANULADOS (44 pos.): fecha, tipo, punto de venta, número y
     * fecha de anulación. Diseño de registro de ARCA (pág. 8 del diseño oficial).
     *
     * @param list<array<string, mixed>> $rows
     */
    public function ventasAnulados(array $rows): string
    {
        return $this->unir(array_map(fn (array $r): string => (new RegistroFijo())
            ->fecha($this->str($r, 'fecha'))
            ->entero($this->cbteTipo($r), 3)
            ->entero($this->str($r, 'punto_venta'), 5)
            ->entero($this->str($r, 'numero'), 20)
            ->fecha($this->str($r, 'fecha_anulacion'))
            ->build(self::LEN_VENTAS_ANULADOS), $rows));
    }

    /** @param array<string, mixed> $r */
    private function cbteTipo(array $r): int
    {
        return CbteTipoResolver::resolve(
            $this->str($r, 'cbte_codigo'),
            $this->str($r, 'letra'),
            $r['cod_citi'] ?? null,
        );
    }

    /** @param array<string, mixed> $r */
    private function alicuota(array $r): int
    {
        return AlicuotaIvaResolver::id($this->str($r, 'iva_alicuota', '0'));
    }

    /** @param array<string, mixed> $r */
    private function str(array $r, string $key, string $default = ''): string
    {
        $v = $r[$key] ?? null;

        return $v === null ? $default : (string) $v;
    }

    /** @param list<string> $lineas */
    private function unir(array $lineas): string
    {
        return $lineas === [] ? '' : implode(self::EOL, $lineas) . self::EOL;
    }
}
