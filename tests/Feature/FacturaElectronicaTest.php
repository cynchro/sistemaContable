<?php

namespace Tests\Feature;

use App\Modules\Iva\Afip\Wsfe\WsfeClient;
use App\Modules\Iva\Afip\Wsfe\ComprobanteCae;

/**
 * Emisión de factura electrónica (WSFEv1): numeración por punto de venta + CAE.
 * Sustituye el WsfeClient real por un doble (sin red ni certificado AFIP).
 */
class FacturaElectronicaTest extends FeatureTestCase
{
    private function seedCatalogos(): void
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, cod_citi, acredita, signo)
             VALUES (1, 'FA', 'Factura', '01', 'N', 1), (3, 'NC', 'Nota de Crédito', '03', 'S', -1)"
        );
        $this->pdo->exec("INSERT INTO tipos_documento (id, nombre, cod_afip) VALUES (1, 'CUIT', 80)");
        $this->pdo->exec(
            "INSERT INTO condiciones_iva (id, codigo, nombre, codigo_afip) VALUES (1, 'RI', 'Resp. Inscripto', '01')"
        );
        $this->pdo->exec("INSERT INTO tipos_moneda (id, codigo_afip, nombre) VALUES (1, 'PES', 'Pesos')");
    }

    /** @return array{auth: array<string,mixed>, e:int, p:int, v:int} */
    private function escenarioConVenta(): array
    {
        $this->seedCatalogos();
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'FE SA'], $auth)['json']['data']['id'];
        $p = (int) $this->postJson("/empresas/{$e}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        $v = (int) $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", [
            'fecha'               => '2026-01-15',
            'tipo_comprobante_id' => 1,
            'tipo_documento_id'   => 1,
            'condicion_iva_id'    => 1,
            'tipo_moneda_id'      => 1,
            'letra'               => 'A',
            'punto_venta'         => '1',
            'cuit'                => '30711111118',
            'discriminaciones'    => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'e' => $e, 'p' => $p, 'v' => $v];
    }

    private function bindWsfe(string $resultado, ?string $cae): void
    {
        $this->app->instance(WsfeClient::class, new class ($resultado, $cae) implements WsfeClient {
            public function __construct(private string $resultado, private ?string $cae)
            {
            }
            public function dummy(): array
            {
                return [];
            }
            public function ultimoAutorizado(int $ptoVta, int $cbteTipo): int
            {
                return 10;
            }
            public function solicitarCae(array $feCaeReq): ComprobanteCae
            {
                if ($this->resultado === 'A') {
                    return new ComprobanteCae('A', $this->cae, '20260131');
                }
                return new ComprobanteCae('R', null, null, [], [['code' => 10016, 'msg' => 'PtoVta inexistente']]);
            }
        });
    }

    public function test_autoriza_y_persiste_cae_y_numero(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->bindWsfe('A', '74000000000001');

        $resp = $this->postJson("/empresas/{$ctx['e']}/periodos/{$ctx['p']}/ventas/{$ctx['v']}/cae", [], $ctx['auth']);

        $this->assertSame(200, $resp['status']);
        $this->assertSame('74000000000001', $resp['json']['data']['cae']);
        $this->assertSame(11, $resp['json']['data']['numero']); // últimoAutorizado(10) + 1
        $this->assertSame('2026-01-31', $resp['json']['data']['cae_vto']);

        $venta = $this->getJson(
            "/empresas/{$ctx['e']}/periodos/{$ctx['p']}/ventas/{$ctx['v']}",
            $ctx['auth'],
        )['json']['data'];
        $this->assertSame('74000000000001', $venta['cae']);
        $this->assertSame('A', $venta['afip_resultado']);
        $this->assertSame('00000011', $venta['numero']);
    }

    public function test_no_reautoriza_si_ya_tiene_cae(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->bindWsfe('A', '74000000000001');
        $url = "/empresas/{$ctx['e']}/periodos/{$ctx['p']}/ventas/{$ctx['v']}/cae";

        $this->assertSame(200, $this->postJson($url, [], $ctx['auth'])['status']);
        $this->assertSame(409, $this->postJson($url, [], $ctx['auth'])['status']);
    }

    public function test_emite_nota_credito_con_comprobantes_asociados(): void
    {
        $this->seedCatalogos();
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'NC SA'], $auth)['json']['data']['id'];
        $p = (int) $this->postJson("/empresas/{$e}/periodos", [
            'nombre' => '2026-02', 'fecha_ini' => '2026-02-01', 'fecha_fin' => '2026-02-28',
        ], $auth)['json']['data']['id'];

        // Nota de crédito (tipo 3) que asocia la Factura A (tipo 1) 0001-00000011.
        $v = (int) $this->postJson("/empresas/{$e}/periodos/{$p}/ventas", [
            'fecha'                  => '2026-02-10',
            'tipo_comprobante_id'    => 3,
            'tipo_documento_id'      => 1,
            'condicion_iva_id'       => 1,
            'tipo_moneda_id'         => 1,
            'letra'                  => 'A',
            'punto_venta'            => '1',
            'cuit'                   => '30711111118',
            'discriminaciones'       => [['neto_gravado' => '100.00', 'iva_alicuota' => '21.000']],
            'comprobantes_asociados' => [
                ['tipo_comprobante_id' => 1, 'letra' => 'A', 'punto_venta' => '1', 'numero' => '11'],
            ],
        ], $auth)['json']['data']['id'];

        // El asociado se persiste en el agregado.
        $venta = $this->getJson("/empresas/{$e}/periodos/{$p}/ventas/{$v}", $auth)['json']['data'];
        $this->assertCount(1, $venta['comprobantes_asociados']);

        // Fake que captura el FeCAEReq enviado a AFIP.
        $captor = new class implements WsfeClient {
            /** @var array<string, mixed> */
            public array $req = [];
            public function dummy(): array
            {
                return [];
            }
            public function ultimoAutorizado(int $ptoVta, int $cbteTipo): int
            {
                return 0;
            }
            public function solicitarCae(array $feCaeReq): ComprobanteCae
            {
                $this->req = $feCaeReq;
                return new ComprobanteCae('A', '74000000000099', '20260228');
            }
        };
        $this->app->instance(WsfeClient::class, $captor);

        $resp = $this->postJson("/empresas/{$e}/periodos/{$p}/ventas/{$v}/cae", [], $auth);
        $this->assertSame(200, $resp['status']);

        $det = $captor->req['FeDetReq']['FECAEDetRequest'][0];
        $this->assertSame(['CbteAsoc' => [['Tipo' => 1, 'PtoVta' => 1, 'Nro' => 11]]], $det['CbtesAsoc']);
    }

    public function test_rechazo_de_afip_devuelve_409_y_persiste_resultado(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->bindWsfe('R', null);

        $resp = $this->postJson("/empresas/{$ctx['e']}/periodos/{$ctx['p']}/ventas/{$ctx['v']}/cae", [], $ctx['auth']);
        $this->assertSame(409, $resp['status']);

        $venta = $this->getJson(
            "/empresas/{$ctx['e']}/periodos/{$ctx['p']}/ventas/{$ctx['v']}",
            $ctx['auth'],
        )['json']['data'];
        $this->assertSame('R', $venta['afip_resultado']);
        $this->assertNull($venta['cae']);
    }
}
