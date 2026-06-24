<?php

namespace Tests\Unit\Modules\Iva\Afip;

use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsfe\ComprobanteCae;

class ComprobanteCaeTest extends UnitTestCase
{
    public function test_parsea_aprobado_con_cae(): void
    {
        $resp = (object) [
            'FeCabResp' => (object) ['Resultado' => 'A'],
            'FeDetResp' => (object) [
                'FECAEDetResponse' => (object) [
                    'Resultado'  => 'A',
                    'CAE'        => '74000000000001',
                    'CAEFchVto'  => '20260131',
                ],
            ],
        ];

        $cae = ComprobanteCae::fromSoapResponse($resp);

        $this->assertTrue($cae->aprobado());
        $this->assertSame('A', $cae->resultado);
        $this->assertSame('74000000000001', $cae->cae);
        $this->assertSame('20260131', $cae->caeVto);
    }

    public function test_parsea_rechazado_con_errores_y_observaciones(): void
    {
        $resp = (object) [
            'FeCabResp' => (object) ['Resultado' => 'R'],
            'FeDetResp' => (object) [
                'FECAEDetResponse' => (object) [
                    'Resultado'     => 'R',
                    'Observaciones' => (object) [
                        'Obs' => (object) ['Code' => 10016, 'Msg' => 'Punto de venta inexistente'],
                    ],
                ],
            ],
            'Errors' => (object) [
                'Err' => [
                    (object) ['Code' => 600, 'Msg' => 'Token expirado'],
                ],
            ],
        ];

        $cae = ComprobanteCae::fromSoapResponse($resp);

        $this->assertFalse($cae->aprobado());
        $this->assertSame('R', $cae->resultado);
        $this->assertNull($cae->cae);
        $this->assertSame([['code' => 10016, 'msg' => 'Punto de venta inexistente']], $cae->observaciones);
        $this->assertSame([['code' => 600, 'msg' => 'Token expirado']], $cae->errores);
    }
}
