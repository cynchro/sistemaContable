<?php

namespace Tests\Feature;

/**
 * DJ IVA Simple — apertura por actividad, Fase 2 (A15): estrategias por ALÍCUOTA
 * (construcción) y por RECEPTOR (cliente/CUIT), con la precedencia
 * override → receptor → punto de venta → alícuota → default.
 */
class DjIvaSimpleActividadFase2Test extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Constructora SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return compact('auth', 'empresaId', 'periodoId');
    }

    private function actividad(array $ctx, string $codigo): int
    {
        return (int) $this->postJson("/empresas/{$ctx['empresaId']}/actividades", [
            'codigo' => $codigo, 'descripcion' => "Act {$codigo}",
        ], $ctx['auth'])['json']['data']['id'];
    }

    public function test_distribucion_por_alicuota_construccion(): void
    {
        $ctx        = $this->escenario();
        $residencial = $this->actividad($ctx, '410011');
        $noResid     = $this->actividad($ctx, '410021');
        $this->postJson("/empresas/{$ctx['empresaId']}/actividades-alicuota", [
            'alicuota' => '10.5', 'actividad_id' => $residencial,
        ], $ctx['auth']);
        $this->postJson("/empresas/{$ctx['empresaId']}/actividades-alicuota", [
            'alicuota' => '21', 'actividad_id' => $noResid,
        ], $ctx['auth']);

        // Una factura con dos líneas: 10,5% (residencial) y 21% (no residencial).
        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'Cliente', 'letra' => 'A', 'punto_venta' => '1', 'numero' => '1',
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '10.500'],
                ['neto_gravado' => '2000.00', 'iva_alicuota' => '21.000'],
            ],
        ], $ctx['auth']);

        $base = "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/dj-iva-simple";
        $raw  = $this->getJson("{$base}/debito-fiscal", $ctx['auth'])['raw'];

        $this->assertStringContainsString("410011;1;1;4;1000;105;0;", $raw);  // 10,5% → residencial
        $this->assertStringContainsString("410021;1;1;5;2000;420;0;", $raw);  // 21% → no residencial
    }

    public function test_por_receptor_tiene_precedencia_sobre_alicuota(): void
    {
        $ctx     = $this->escenario();
        $noResid = $this->actividad($ctx, '410021');
        $minera  = $this->actividad($ctx, '990000');
        // 21% mapea a no residencial...
        $this->postJson("/empresas/{$ctx['empresaId']}/actividades-alicuota", [
            'alicuota' => '21', 'actividad_id' => $noResid,
        ], $ctx['auth']);
        // ...pero todo lo facturado a este cliente va a "minería" (receptor gana).
        $clienteId = (int) $this->postJson("/empresas/{$ctx['empresaId']}/clientes", [
            'nombre' => 'Minera Galaxy', 'cuit' => '30710968973', 'condicion_iva_id' => 1,
        ], $ctx['auth'])['json']['data']['id'];
        $this->postJson("/empresas/{$ctx['empresaId']}/actividades-receptor", [
            'cliente_id' => $clienteId, 'actividad_id' => $minera,
        ], $ctx['auth']);

        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_id' => $clienteId, 'cliente_nombre' => 'Minera Galaxy',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => '2',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $ctx['auth']);

        $base = "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/dj-iva-simple";
        $raw  = $this->getJson("{$base}/debito-fiscal", $ctx['auth'])['raw'];

        $this->assertStringContainsString("990000;1;1;5;1000;210;0;", $raw);   // receptor → minería
        $this->assertStringNotContainsString('410021;', $raw);                 // NO cae en no residencial
    }
}
