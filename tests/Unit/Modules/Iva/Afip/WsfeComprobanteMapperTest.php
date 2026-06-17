<?php

namespace Tests\Unit\Modules\Iva\Afip;

use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsfe\FacturaContexto;
use App\Modules\Iva\Afip\Wsfe\WsfeComprobanteMapper;

class WsfeComprobanteMapperTest extends UnitTestCase
{
    public function test_arma_fecaereq_desde_la_venta(): void
    {
        $venta = [
            'fecha'        => '2026-01-15',
            'cuit'         => '30-71111111-8',
            'concepto'     => 1,
            'neto_no_grav' => '100.00',
            'exento'       => '0.00',
            'imp_interno'  => '0.00',
            'total'        => '1862.50',
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'iva_importe' => '210.00'],
                ['neto_gravado' => '500.00',  'iva_alicuota' => '10.500', 'iva_importe' => '52.50'],
            ],
        ];
        $ctx = new FacturaContexto(
            ptoVta: 1,
            numero: 11,
            cbteTipo: 1,
            docTipo: 80,
            condicionCodigo: 'RI',
        );

        $req = (new WsfeComprobanteMapper())->build($venta, $ctx);

        $this->assertSame(['CantReg' => 1, 'PtoVta' => 1, 'CbteTipo' => 1], $req['FeCabReq']);

        $det = $req['FeDetReq']['FECAEDetRequest'][0];
        $this->assertSame(1, $det['Concepto']);
        $this->assertSame(80, $det['DocTipo']);
        $this->assertSame(30711111118, $det['DocNro']);
        $this->assertSame(11, $det['CbteDesde']);
        $this->assertSame(11, $det['CbteHasta']);
        $this->assertSame('20260115', $det['CbteFch']);
        $this->assertSame(1500.0, $det['ImpNeto']);
        $this->assertSame(262.5, $det['ImpIVA']);
        $this->assertSame(100.0, $det['ImpTotConc']);
        $this->assertSame(1862.5, $det['ImpTotal']);
        $this->assertSame('PES', $det['MonId']);
        $this->assertSame(1, $det['CondicionIVAReceptorId']);

        // Iva: envuelto en AlicIva (ArrayOfAlicIva del WSDL); 21%→5, 10.5%→4.
        $this->assertSame(
            [
                'AlicIva' => [
                    ['Id' => 5, 'BaseImp' => 1000.0, 'Importe' => 210.0],
                    ['Id' => 4, 'BaseImp' => 500.0, 'Importe' => 52.5],
                ],
            ],
            $det['Iva'],
        );
        $this->assertArrayNotHasKey('CbtesAsoc', $det);
        $this->assertArrayNotHasKey('Tributos', $det); // sin imp_interno → sin tributos
    }

    public function test_impuestos_internos_se_emiten_como_tributo(): void
    {
        $venta = [
            'fecha' => '2026-01-15', 'cuit' => '30711111118', 'concepto' => 1,
            'imp_interno' => '50.00', 'total' => '0.00',
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'iva_importe' => '210.00'],
            ],
        ];
        $ctx = new FacturaContexto(1, 11, 1, 80, 'RI');

        $det = (new WsfeComprobanteMapper())->build($venta, $ctx)['FeDetReq']['FECAEDetRequest'][0];

        $this->assertSame(50.0, $det['ImpTrib']);
        $this->assertSame(
            ['Tributo' => [
                ['Id' => 4, 'Desc' => 'Impuestos internos', 'BaseImp' => 0.0, 'Alic' => 0.0, 'Importe' => 50.0],
            ]],
            $det['Tributos'],
        );
    }

    public function test_incluye_comprobantes_asociados(): void
    {
        $venta = ['fecha' => '2026-02-10', 'cuit' => '30711111118', 'concepto' => 1,
                  'total' => '0.00', 'discriminaciones' => []];
        $ctx = new FacturaContexto(
            ptoVta: 1,
            numero: 3,
            cbteTipo: 3,   // NC A
            docTipo: 80,
            condicionCodigo: 'RI',
            cbtesAsoc: [['Tipo' => 1, 'PtoVta' => 1, 'Nro' => 11]],
        );

        $det = (new WsfeComprobanteMapper())->build($venta, $ctx)['FeDetReq']['FECAEDetRequest'][0];

        $this->assertSame(['CbteAsoc' => [['Tipo' => 1, 'PtoVta' => 1, 'Nro' => 11]]], $det['CbtesAsoc']);
    }

    public function test_concepto_servicios_agrega_fechas(): void
    {
        $venta = [
            'fecha' => '2026-01-15', 'cuit' => '20111111112', 'concepto' => 2,
            'total' => '0.00', 'discriminaciones' => [],
            'fch_serv_desde' => '2026-01-01', 'fch_serv_hasta' => '2026-01-31', 'fch_vto_pago' => '2026-02-10',
        ];
        $ctx = new FacturaContexto(1, 5, 11, 80, 'CF');

        $det = (new WsfeComprobanteMapper())->build($venta, $ctx)['FeDetReq']['FECAEDetRequest'][0];

        $this->assertSame('20260101', $det['FchServDesde']);
        $this->assertSame('20260131', $det['FchServHasta']);
        $this->assertSame('20260210', $det['FchVtoPago']);
    }
}
