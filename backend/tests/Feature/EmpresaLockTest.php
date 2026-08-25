<?php

namespace Tests\Feature;

/**
 * "Ocupación" de empresa (WhatsApp con el cliente, 11/08/2026): un usuario a la vez trabaja
 * sobre un contribuyente. Un usuario NO-admin queda bloqueado del todo si otro ya la tiene
 * ocupada; un admin entra en modo observador (lectura sí, escritura no).
 *
 * Se prueba contra rutas de Compartido (`/empresas/{id}`) porque no tienen `PermissionMiddleware`
 * de por medio — aísla el comportamiento del lock sin mezclarlo con permisos por recurso.
 */
class EmpresaLockTest extends FeatureTestCase
{
    public function test_usuario_ocupa_una_empresa_libre(): void
    {
        $auth      = $this->bearer($this->actingAsUser(null, 0)['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock A'], $auth)['json']['data']['id'];

        $r = $this->postJson("/empresas/{$empresaId}/ocupar", [], $auth);
        $this->assertSame(200, $r['status']);
        $this->assertSame('propio', $r['json']['data']['modo']);
    }

    public function test_otro_usuario_no_admin_queda_bloqueado_mientras_esta_ocupada(): void
    {
        $tenantId  = $this->seedTenant();
        $userA     = $this->actingAsUser($tenantId, 0);
        $userB     = $this->actingAsUser($tenantId, 0);
        $authA     = $this->bearer($userA['token']);
        $authB     = $this->bearer($userB['token']);

        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock B'], $authA)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/ocupar", [], $authA);

        $get = $this->getJson("/empresas/{$empresaId}", $authB);
        $this->assertSame(409, $get['status']);

        $put = $this->putJson("/empresas/{$empresaId}", ['nombre' => 'Intento B'], $authB);
        $this->assertSame(409, $put['status']);
    }

    public function test_admin_entra_en_modo_observador_pero_no_puede_escribir(): void
    {
        $tenantId = $this->seedTenant();
        $usuario  = $this->actingAsUser($tenantId, 0);
        $admin    = $this->actingAsUser($tenantId, 1);
        $authUsuario = $this->bearer($usuario['token']);
        $authAdmin   = $this->bearer($admin['token']);

        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock C'], $authUsuario)
            ['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/ocupar", [], $authUsuario);

        // El admin puede leer (modo observador).
        $get = $this->getJson("/empresas/{$empresaId}", $authAdmin);
        $this->assertSame(200, $get['status']);

        // Pero no puede escribir.
        $put = $this->putJson("/empresas/{$empresaId}", ['nombre' => 'Intento Admin'], $authAdmin);
        $this->assertSame(403, $put['status']);

        // Y su propio /ocupar informa el modo observador, sin robarle el lock al usuario.
        $ocupar = $this->postJson("/empresas/{$empresaId}/ocupar", [], $authAdmin);
        $this->assertSame(200, $ocupar['status']);
        $this->assertSame('observador', $ocupar['json']['data']['modo']);
    }

    public function test_dueño_del_lock_sigue_operando_normal(): void
    {
        $auth      = $this->bearer($this->actingAsUser(null, 0)['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock D'], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/ocupar", [], $auth);

        $get = $this->getJson("/empresas/{$empresaId}", $auth);
        $this->assertSame(200, $get['status']);

        $put = $this->putJson("/empresas/{$empresaId}", ['nombre' => 'Empresa Lock D editada'], $auth);
        $this->assertSame(200, $put['status']);
    }

    public function test_liberar_permite_que_otro_ocupe(): void
    {
        $tenantId = $this->seedTenant();
        $userA    = $this->actingAsUser($tenantId, 0);
        $userB    = $this->actingAsUser($tenantId, 0);
        $authA    = $this->bearer($userA['token']);
        $authB    = $this->bearer($userB['token']);

        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock E'], $authA)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/ocupar", [], $authA);
        $this->postJson("/empresas/{$empresaId}/liberar", [], $authA);

        $r = $this->postJson("/empresas/{$empresaId}/ocupar", [], $authB);
        $this->assertSame(200, $r['status']);
        $this->assertSame('propio', $r['json']['data']['modo']);
    }

    public function test_ping_no_falla_y_confirma_ok(): void
    {
        $auth      = $this->bearer($this->actingAsUser(null, 0)['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock F'], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/ocupar", [], $auth);

        $r = $this->postJson("/empresas/{$empresaId}/ping", [], $auth);
        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['data']['ok']);
    }

    public function test_no_afecta_a_una_empresa_libre(): void
    {
        $auth      = $this->bearer($this->actingAsUser(null, 0)['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock G'], $auth)['json']['data']['id'];

        $get = $this->getJson("/empresas/{$empresaId}", $auth);
        $this->assertSame(200, $get['status']);
    }

    /**
     * Bug real encontrado en vivo (25/08/2026, botón "Liquidar IVA"): el worker (API key) pega
     * contra rutas del módulo Iva de la MISMA empresa que el usuario tiene abierta en su propio
     * navegador (típicamente así — es cómo llega a tocar el botón) — el candado humano no debe
     * bloquearlo, la API key ya está acotada por sus propios scopes.
     */
    public function test_api_key_no_queda_bloqueada_por_el_candado_humano(): void
    {
        $ctx       = $this->actingAsUser();
        $auth      = $this->bearer($ctx['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Lock H'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => 'AGOSTO 2026', 'fecha_ini' => '2026-08-01', 'fecha_fin' => '2026-08-31',
        ], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$empresaId}/ocupar", [], $auth);

        $key = $this->postJson('/api-keys', ['name' => 'worker-test', 'scopes' => ['iva.compras']], $auth);
        $workerAuth = $this->bearer((string) $key['json']['data']['token']);

        $r = $this->getJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", $workerAuth);
        $this->assertSame(200, $r['status']);
    }
}
