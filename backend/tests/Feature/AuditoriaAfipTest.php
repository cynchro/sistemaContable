<?php

namespace Tests\Feature;

use App\Modules\Iva\Afip\Wsfe\WsfeClient;
use App\Modules\Iva\Afip\Wsfe\ComprobanteCae;
use App\Modules\Iva\Afip\Wsfe\ComprobanteConsultado;

/**
 * Auditoría de ventas vs. ARCA (WSFEv1): compara el último número autorizado en AFIP
 * contra el máximo cargado localmente, por punto de venta + tipo + letra. Sustituye el
 * WsfeClient real por un doble (sin red ni certificado AFIP).
 */
class AuditoriaAfipTest extends FeatureTestCase
{
    private function bindWsfe(int $ultimoArca, ?ComprobanteConsultado $detalle = null): void
    {
        $this->app->instance(WsfeClient::class, new class ($ultimoArca, $detalle) implements WsfeClient {
            public function __construct(private int $ultimoArca, private ?ComprobanteConsultado $detalle)
            {
            }
            public function dummy(): array
            {
                return [];
            }
            public function ultimoAutorizado(int $ptoVta, int $cbteTipo): int
            {
                return $this->ultimoArca;
            }
            public function solicitarCae(array $feCaeReq): ComprobanteCae
            {
                return new ComprobanteCae('A', '74000000000001', '20260131');
            }
            public function consultarComprobante(int $ptoVta, int $cbteTipo, int $cbteNro): ComprobanteConsultado
            {
                return $this->detalle ?? new ComprobanteConsultado(false);
            }
        });
    }

    /** @return array{auth: array<string,mixed>, e:int, p:int, v:int} */
    private function escenarioConVenta(): array
    {
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, cod_citi, acredita, signo)
             VALUES (1, 'FA', 'Factura', '01', 'N', 1)"
        );
        $this->pdo->exec("INSERT INTO tipos_documento (id, nombre, cod_afip) VALUES (1, 'CUIT', 80)");
        $this->pdo->exec(
            "INSERT INTO condiciones_iva (id, codigo, nombre, codigo_afip) VALUES (1, 'RI', 'Resp. Inscripto', '01')"
        );
        $this->pdo->exec("INSERT INTO tipos_moneda (id, codigo_afip, nombre) VALUES (1, 'PES', 'Pesos')");

        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'Auditoria SA'], $auth)['json']['data']['id'];
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
            'numero'              => '11',
            'cuit'                => '30711111118',
            'discriminaciones'    => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'e' => $e, 'p' => $p, 'v' => $v];
    }

    public function test_combo_al_dia_no_reporta_faltantes(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->bindWsfe(11);

        $resp = $this->getJson("/empresas/{$ctx['e']}/auditoria-afip", $ctx['auth']);

        $this->assertSame(200, $resp['status']);
        $filas = $resp['json']['data'];
        $this->assertCount(1, $filas);
        $this->assertSame('1', $filas[0]['punto_venta']);
        $this->assertSame(1, $filas[0]['tipo_comprobante_id']);
        $this->assertSame('FA', $filas[0]['tipo_comprobante']);
        $this->assertSame('A', $filas[0]['letra']);
        $this->assertSame(11, $filas[0]['ultimo_arca']);
        $this->assertSame(11, $filas[0]['ultimo_local']);
        $this->assertSame(0, $filas[0]['faltantes']);
    }

    public function test_reporta_faltantes_cuando_arca_tiene_mas_numeros(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->bindWsfe(15);

        $filas = $this->getJson("/empresas/{$ctx['e']}/auditoria-afip", $ctx['auth'])['json']['data'];

        $this->assertSame(15, $filas[0]['ultimo_arca']);
        $this->assertSame(11, $filas[0]['ultimo_local']);
        $this->assertSame(4, $filas[0]['faltantes']);
    }

    public function test_detalle_de_comprobante_no_encontrado_en_arca(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->bindWsfe(11, new ComprobanteConsultado(false));

        $resp = $this->getJson(
            "/empresas/{$ctx['e']}/auditoria-afip/comprobante"
                . '?tipo_comprobante_id=1&punto_venta=1&letra=A&numero=12',
            $ctx['auth'],
        );

        $this->assertSame(200, $resp['status']);
        $this->assertFalse($resp['json']['data']['encontrado']);
        $this->assertFalse($resp['json']['data']['ya_cargado']);
    }

    public function test_detalle_de_comprobante_ya_cargado_localmente(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->bindWsfe(11, new ComprobanteConsultado(
            encontrado: true,
            resultado: 'A',
            fecha: '2026-01-15',
            impTotal: 1210.0,
            impNeto: 1000.0,
            cae: '74000000000001',
            caeVto: '2026-01-31',
        ));

        $resp = $this->getJson(
            "/empresas/{$ctx['e']}/auditoria-afip/comprobante"
                . '?tipo_comprobante_id=1&punto_venta=1&letra=A&numero=11',
            $ctx['auth'],
        );

        $data = $resp['json']['data'];
        $this->assertTrue($data['encontrado']);
        $this->assertTrue($data['ya_cargado']);
        $this->assertSame($ctx['v'], $data['venta_id_local']);
        $this->assertEqualsWithDelta(1210.0, $data['total'], 0.001);
    }

    public function test_rol_sin_permiso_recibe_403(): void
    {
        $ctx = $this->escenarioConVenta();
        $this->pdo->exec("INSERT IGNORE INTO roles (id, nombre, estado) VALUES (78, 'Rol78', 'activo')");
        $auth = $this->bearer($this->actingAsUser(null, 78)['token']);

        $this->assertSame(403, $this->getJson("/empresas/{$ctx['e']}/auditoria-afip", $auth)['status']);
    }
}
