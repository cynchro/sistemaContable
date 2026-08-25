<?php

namespace Tests\Unit\Modules\Iva\Export;

use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Export\LibroIvaDigitalWriter;

/**
 * Libro IVA Digital: las posiciones de cada campo se validan contra el diseño de
 * registro de ARCA (`imagenes/disenio_registro_IVA_digital.pdf`) y los TXT de ejemplo.
 * Los offsets son base 0 (campo «desde» del PDF menos 1).
 */
class LibroIvaDigitalWriterTest extends UnitTestCase
{
    private LibroIvaDigitalWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new LibroIvaDigitalWriter();
    }

    public function test_ventas_cbte_respeta_el_diseno_de_266(): void
    {
        $row = [
            'fecha' => '2026-05-12', 'cbte_codigo' => 'FA', 'letra' => 'A',
            'punto_venta' => '2', 'numero' => '40', 'numero_fin' => '40',
            'doc_cod_afip' => '80', 'cuit' => '30711217920',
            'cliente_nombre' => 'El Satélite Sociedad de Responsabilidad', // se trunca/MAYÚSC/sin acento
            'total' => '7169985.68', 'neto_no_grav' => '0', 'exento' => '0',
            'perc_no_cat' => '0', 'perc_nac' => '0', 'perc_iibb' => '37.50', 'perc_muni' => '0',
            'imp_interno' => '0', 'moneda_codigo' => 'PES', 'tipo_cambio' => null, 'cant_alic' => '1',
        ];

        $out = $this->writer->ventasCbte([$row]);
        $this->assertStringEndsWith("\r\n", $out);
        $line = substr($out, 0, 266);

        $this->assertSame(266, strlen($line));
        $this->assertSame('20260512', substr($line, 0, 8));            // fecha
        $this->assertSame('001', substr($line, 8, 3));                 // FA + A → CbteTipo 1
        $this->assertSame('00002', substr($line, 11, 5));              // punto de venta
        $this->assertSame('00000000000000000040', substr($line, 16, 20)); // número
        $this->assertSame('80', substr($line, 56, 2));                // doc comprador (CUIT)
        $this->assertSame('00000000030711217920', substr($line, 58, 20)); // CUIT comprador
        $this->assertSame('EL SATELITE SOCIEDAD DE RESPON', substr($line, 78, 30)); // 30, sin acento
        $this->assertSame('000000716998568', substr($line, 108, 15)); // importe total 7169985.68
        $this->assertSame('000000000003750', substr($line, 183, 15)); // perc. IIBB (campo 14) = 37.50
        $this->assertSame('PES', substr($line, 228, 3));               // moneda
        $this->assertSame('0001000000', substr($line, 231, 10));       // tipo de cambio 1.0
        $this->assertSame('1', substr($line, 241, 1));                 // cantidad de alícuotas
    }

    public function test_ventas_alicuotas_respeta_el_diseno_de_62(): void
    {
        $row = [
            'cbte_codigo' => 'FA', 'letra' => 'A', 'punto_venta' => '2', 'numero' => '40',
            'neto_gravado' => '5925608.00', 'iva_alicuota' => '21.000', 'iva_importe' => '1244377.68',
        ];

        $line = substr($this->writer->ventasAlicuotas([$row]), 0, 62);

        $this->assertSame(62, strlen($line));
        $this->assertSame('001', substr($line, 0, 3));                 // CbteTipo
        $this->assertSame('00002', substr($line, 3, 5));               // punto de venta
        $this->assertSame('00000000000000000040', substr($line, 8, 20)); // número
        $this->assertSame('000000592560800', substr($line, 28, 15));   // neto gravado
        $this->assertSame('0005', substr($line, 43, 4));               // alícuota 21% → Id 5
        $this->assertSame('000000124437768', substr($line, 47, 15));   // impuesto liquidado
    }

    public function test_compras_cbte_respeta_el_diseno_de_325(): void
    {
        $row = [
            'fecha' => '2026-05-30', 'cbte_codigo' => 'FA', 'letra' => 'A',
            'punto_venta' => '5', 'numero' => '23453', 'cuit' => '30710968973',
            'proveedor_nombre' => 'Aberturas Herfasa SA', 'total' => '6925274.02',
            'neto_no_grav' => '0', 'exento' => '0', 'imp_interno' => '0',
            'perc_iva' => '0', 'perc_nac' => '0', 'perc_iibb' => '0', 'perc_muni' => '0',
            'moneda_codigo' => 'PES', 'tipo_cambio' => null, 'cant_alic' => '1',
            'cf_computable' => '1201907.06',
        ];

        $line = substr($this->writer->comprasCbte([$row]), 0, 325);

        $this->assertSame(325, strlen($line));
        $this->assertSame('20260530', substr($line, 0, 8));
        $this->assertSame('001', substr($line, 8, 3));
        $this->assertSame('00005', substr($line, 11, 5));
        $this->assertSame('80', substr($line, 52, 2));                 // doc vendedor (CUIT)
        $this->assertSame('00000000030710968973', substr($line, 54, 20));
        $this->assertSame('000000692527402', substr($line, 104, 15));  // importe total
        $this->assertSame('000000120190706', substr($line, 239, 15));  // crédito fiscal computable
    }

    public function test_compras_alicuotas_respeta_el_diseno_de_84(): void
    {
        $row = [
            'cbte_codigo' => 'FA', 'letra' => 'A', 'punto_venta' => '5', 'numero' => '23453',
            'cuit' => '30710968973', 'neto_gravado' => '5723366.96',
            'iva_alicuota' => '21.000', 'iva_importe' => '1201907.06',
        ];

        $line = substr($this->writer->comprasAlicuotas([$row]), 0, 84);

        $this->assertSame(84, strlen($line));
        $this->assertSame('001', substr($line, 0, 3));
        $this->assertSame('00005', substr($line, 3, 5));
        $this->assertSame('00000000000000023453', substr($line, 8, 20));
        $this->assertSame('80', substr($line, 28, 2));
        $this->assertSame('00000000030710968973', substr($line, 30, 20));
        $this->assertSame('000000572336696', substr($line, 50, 15));
        $this->assertSame('0005', substr($line, 65, 4));
        $this->assertSame('000000120190706', substr($line, 69, 15));
    }

    public function test_periodo_sin_comprobantes_da_vacio(): void
    {
        $this->assertSame('', $this->writer->ventasCbte([]));
    }

    /**
     * Caso real RED COLON S.A. (25/08/2026): Factura A 100% Exento, sin ninguna
     * discriminación gravada. El spec oficial de ARCA exige `cant_alic='1'` +
     * `Código de operación='E'` juntos (ver docblock de
     * `LibroIvaDigitalRepository::CANT_ALIC_SQL`/`CODIGO_OPERACION_SQL`).
     */
    public function test_compras_cbte_comprobante_exento_lleva_cant_alic_1_y_codigo_e(): void
    {
        $row = [
            'fecha' => '2026-08-18', 'cbte_codigo' => 'FA', 'letra' => 'A',
            'punto_venta' => '210', 'numero' => '602', 'cuit' => '30668069904',
            'proveedor_nombre' => 'Red Colon SA', 'total' => '53196.95',
            'neto_no_grav' => '0', 'exento' => '53196.95', 'imp_interno' => '0',
            'perc_iva' => '0', 'perc_nac' => '0', 'perc_iibb' => '0', 'perc_muni' => '0',
            'moneda_codigo' => 'PES', 'tipo_cambio' => null,
            'cant_alic' => '1', 'codigo_operacion' => 'E', 'cf_computable' => '0',
        ];

        $line = substr($this->writer->comprasCbte([$row]), 0, 325);

        $this->assertSame('1', substr($line, 237, 1));  // cantidad de alícuotas
        $this->assertSame('E', substr($line, 238, 1));  // código de operación
    }

    /** Comprobante letra B/C: `cant_alic` fijo en '0' (no discrimina IVA por diseño). */
    public function test_compras_cbte_letra_b_lleva_cant_alic_0_y_codigo_operacion_blanco(): void
    {
        $row = [
            'fecha' => '2026-08-18', 'cbte_codigo' => 'FA', 'letra' => 'B',
            'punto_venta' => '1', 'numero' => '1', 'cuit' => '30668069904',
            'proveedor_nombre' => 'Proveedor SA', 'total' => '1000',
            'cant_alic' => '0', 'codigo_operacion' => '',
        ];

        $line = substr($this->writer->comprasCbte([$row]), 0, 325);

        $this->assertSame('0', substr($line, 237, 1));
        $this->assertSame(' ', substr($line, 238, 1));
    }

    /**
     * Tipos administrativos raros de RG1415 (OC/30/CL/LP/LN/TD/PA/DA/CC/EX) — el código
     * legacy de texto es ambiguo (varios ids comparten 'OC' con distinto CbteTipo real),
     * así que `CbteTipoResolver` cae al `cod_citi` de la fila (25/08/2026). Caso real:
     * id 72 'OC' → cod_citi '40' ("Agregado por importación").
     */
    public function test_compras_cbte_tipo_administrativo_raro_resuelve_por_cod_citi(): void
    {
        $row = [
            'fecha' => '2026-08-18', 'cbte_codigo' => 'OC', 'cod_citi' => '40', 'letra' => 'A',
            'punto_venta' => '1', 'numero' => '1', 'cuit' => '30668069904',
            'proveedor_nombre' => 'Proveedor SA', 'total' => '1000', 'cant_alic' => '0',
        ];

        $line = substr($this->writer->comprasCbte([$row]), 0, 325);

        $this->assertSame('040', substr($line, 8, 3)); // CbteTipo resuelto por cod_citi
    }
}
