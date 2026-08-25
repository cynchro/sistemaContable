<?php

namespace Tests\Feature;

/**
 * Botón "Liquidar IVA" (plan 25/08/2026): el usuario pide una liquidación, el worker externo
 * del bot (autenticado por API key con scopes propios) la toma y reporta el resultado. Cubre
 * las validaciones de alta (credencial/CUIT faltante, duplicado) y el ciclo completo
 * pendiente→tomada→terminada vía los endpoints del worker, con aislamiento por tenant.
 */
class LiquidacionIvaTest extends FeatureTestCase
{
    /** @return array{empresaId: int, periodoId: int, auth: array<string, string>} */
    private function empresaConPeriodo(array $auth, string $cuit = '20111111112'): array
    {
        $empresaId = (int) $this->postJson('/empresas', [
            'nombre' => 'Acevedo Mario Ramon',
            'cuit'   => $cuit,
        ], $auth)['json']['data']['id'];

        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => 'AGOSTO 2026',
            'fecha_ini' => '2026-08-01',
            'fecha_fin' => '2026-08-31',
        ], $auth)['json']['data']['id'];

        return ['empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    private function cargarCredencialFiscal(int $empresaId, array $auth): void
    {
        $resp = $this->postJson("/empresas/{$empresaId}/credenciales", [
            'tipo'    => 'fiscal',
            'sistema' => 'AFIP',
            'usuario' => '20111111112',
            'clave'   => 'clave-de-prueba-no-real',
        ], $auth);

        $this->assertSame(201, $resp['status']);
    }

    /** API key del bot worker, con los 2 scopes nuevos. */
    private function bearerWorker(array $auth): array
    {
        $created = $this->postJson('/api-keys', [
            'name'   => 'bot-liquidar-iva-test',
            'scopes' => ['iva.liquidaciones.worker', 'iva.liquidaciones.credencial'],
        ], $auth);

        return $this->bearer((string) $created['json']['data']['token']);
    }

    public function test_alta_sin_credencial_fiscal_falla_con_422(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $ctx  = $this->empresaConPeriodo($auth);

        $resp = $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/liquidaciones",
            ['direccion' => 'subir', 'libro' => 'compras'],
            $auth,
        );

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('credencial', $resp['json']['errors']);
    }

    public function test_alta_sin_cuit_en_la_empresa_falla_con_422(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Sin CUIT'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => 'AGOSTO 2026', 'fecha_ini' => '2026-08-01', 'fecha_fin' => '2026-08-31',
        ], $auth)['json']['data']['id'];
        $this->cargarCredencialFiscal($empresaId, $auth);

        $resp = $this->postJson(
            "/empresas/{$empresaId}/periodos/{$periodoId}/liquidaciones",
            ['direccion' => 'subir', 'libro' => 'compras'],
            $auth,
        );

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('cuit', $resp['json']['errors']);
    }

    public function test_no_se_puede_duplicar_una_liquidacion_abierta(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $ctx  = $this->empresaConPeriodo($auth);
        $this->cargarCredencialFiscal($ctx['empresaId'], $auth);

        $primera = $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/liquidaciones",
            ['direccion' => 'ambos', 'libro' => 'ambos'],
            $auth,
        );
        $this->assertSame(201, $primera['status']);
        $this->assertSame('pendiente', $primera['json']['data']['estado']);
        // periodo_arca derivado de fecha_ini (2026-08-01) → 08/2026, no lo pide el usuario.
        $this->assertSame('08/2026', $primera['json']['data']['periodo_arca']);

        $segunda = $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/liquidaciones",
            ['direccion' => 'traer', 'libro' => 'ventas'],
            $auth,
        );
        $this->assertSame(409, $segunda['status']);
    }

    public function test_direccion_invalida_falla_con_422(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $ctx  = $this->empresaConPeriodo($auth);
        $this->cargarCredencialFiscal($ctx['empresaId'], $auth);

        $resp = $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/liquidaciones",
            ['direccion' => 'no-existe', 'libro' => 'ventas'],
            $auth,
        );

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('direccion', $resp['json']['errors']);
    }

    public function test_ciclo_completo_pendiente_tomada_terminada_via_worker(): void
    {
        $userCtx   = $this->actingAsUser();
        $auth      = $this->bearer($userCtx['token']);
        $ctx       = $this->empresaConPeriodo($auth);
        $this->cargarCredencialFiscal($ctx['empresaId'], $auth);

        $creada = $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/liquidaciones",
            ['direccion' => 'subir', 'libro' => 'compras'],
            $auth,
        );
        $id = (int) $creada['json']['data']['id'];

        $workerAuth = $this->bearerWorker($auth);

        // El worker toma la pendiente — sin conocer el id de antemano.
        $tomada = $this->getJson('/iva/liquidaciones/pendientes', $workerAuth);
        $this->assertSame(200, $tomada['status']);
        $this->assertSame($id, $tomada['json']['data']['liquidacion']['id']);
        $this->assertSame('20111111112', $tomada['json']['data']['liquidacion']['cuit']);
        $this->assertSame('tomada', $tomada['json']['data']['liquidacion']['estado']);

        // Nada más pendiente ahora.
        $vacio = $this->getJson('/iva/liquidaciones/pendientes', $workerAuth);
        $this->assertNull($vacio['json']['data']['liquidacion']);

        // Pide la Clave Fiscal (sesión Playwright "expirada", caso simulado).
        $cred = $this->postJson("/iva/liquidaciones/{$id}/credencial", [], $workerAuth);
        $this->assertSame(200, $cred['status']);
        $this->assertSame('20111111112', $cred['json']['data']['cuit']);
        $this->assertSame('clave-de-prueba-no-real', $cred['json']['data']['clave']);

        // Reporta en_curso, después terminada con resultado (objeto → se guarda como JSON).
        $enCurso = $this->postJson("/iva/liquidaciones/{$id}/estado", ['estado' => 'en_curso'], $workerAuth);
        $this->assertSame(200, $enCurso['status']);

        $terminada = $this->postJson("/iva/liquidaciones/{$id}/estado", [
            'estado'    => 'terminada',
            'resultado' => ['compras' => ['agregados' => 5, 'errores' => 0]],
        ], $workerAuth);
        $this->assertSame(200, $terminada['status']);

        // El usuario ve el estado final vía el endpoint normal.
        $show = $this->getJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/liquidaciones/{$id}",
            $auth,
        );
        $this->assertSame('terminada', $show['json']['data']['estado']);
        $this->assertStringContainsString('"agregados":5', (string) $show['json']['data']['resultado']);
    }

    public function test_worker_de_otro_tenant_no_ve_la_liquidacion(): void
    {
        $authA = $this->bearer($this->actingAsUser()['token']);
        $ctx   = $this->empresaConPeriodo($authA);
        $this->cargarCredencialFiscal($ctx['empresaId'], $authA);
        $this->postJson(
            "/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/liquidaciones",
            ['direccion' => 'subir', 'libro' => 'compras'],
            $authA,
        );

        // Otro tenant, su propio worker: no debe ver nada pendiente del primero.
        $authB       = $this->bearer($this->actingAsUser()['token']);
        $workerAuthB = $this->bearerWorker($authB);

        $resp = $this->getJson('/iva/liquidaciones/pendientes', $workerAuthB);
        $this->assertNull($resp['json']['data']['liquidacion']);
    }

    public function test_credenciales_requiere_permiso_para_rol_no_admin(): void
    {
        $this->pdo->exec("INSERT IGNORE INTO roles (id, nombre, estado) VALUES (90, 'RolSinPermiso', 'activo')");
        $auth = $this->bearer($this->actingAsUser(null, 90)['token']);

        $resp = $this->getJson('/empresas/1/credenciales', $auth);

        $this->assertSame(403, $resp['status']);
    }
}
