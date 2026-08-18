<?php

namespace Tests\Feature;

/**
 * Empresas "asignadas" a un usuario (WhatsApp con el cliente, 11/08/2026: "me gustaría que de
 * última él pueda ver sus empresas asignadas... desde usuarios los límites"). Filtro de
 * visibilidad opcional en el listado de empresas — no una restricción dura.
 */
class EmpresaAsignadaTest extends FeatureTestCase
{
    public function test_admin_asigna_empresas_y_se_pueden_consultar(): void
    {
        $tenantId = $this->seedTenant();
        $admin    = $this->actingAsUser($tenantId, 1);
        $usuario  = $this->actingAsUser($tenantId, 0);
        $authAdmin = $this->bearer($admin['token']);

        $e1 = (int) $this->postJson('/empresas', ['nombre' => 'Asignada A'], $authAdmin)['json']['data']['id'];
        $e2 = (int) $this->postJson('/empresas', ['nombre' => 'Asignada B'], $authAdmin)['json']['data']['id'];
        $this->postJson('/empresas', ['nombre' => 'No asignada C'], $authAdmin);

        $put = $this->putJson(
            "/admin/users/{$usuario['userId']}/empresas",
            ['empresa_ids' => [$e1, $e2]],
            $authAdmin,
        );
        $this->assertSame(200, $put['status']);

        $get = $this->getJson("/admin/users/{$usuario['userId']}/empresas", $authAdmin);
        $this->assertCount(2, $get['json']['data']);
    }

    public function test_usuario_con_asignaciones_solo_ve_sus_empresas(): void
    {
        $tenantId = $this->seedTenant();
        $admin    = $this->actingAsUser($tenantId, 1);
        $usuario  = $this->actingAsUser($tenantId, 0);
        $authAdmin   = $this->bearer($admin['token']);
        $authUsuario = $this->bearer($usuario['token']);

        $e1 = (int) $this->postJson('/empresas', ['nombre' => 'Filtro A'], $authAdmin)['json']['data']['id'];
        $this->postJson('/empresas', ['nombre' => 'Filtro B (no asignada)'], $authAdmin);

        $this->putJson("/admin/users/{$usuario['userId']}/empresas", ['empresa_ids' => [$e1]], $authAdmin);

        $lista = $this->getJson('/empresas', $authUsuario);
        $this->assertCount(1, $lista['json']['data']);
        $this->assertSame($e1, $lista['json']['data'][0]['id']);
    }

    public function test_usuario_sin_asignaciones_ve_todas(): void
    {
        $tenantId = $this->seedTenant();
        $usuario  = $this->actingAsUser($tenantId, 0);
        $authUsuario = $this->bearer($usuario['token']);

        $this->postJson('/empresas', ['nombre' => 'Sin filtro A'], $authUsuario);
        $this->postJson('/empresas', ['nombre' => 'Sin filtro B'], $authUsuario);

        $lista = $this->getJson('/empresas', $authUsuario);
        $this->assertCount(2, $lista['json']['data']);
    }

    public function test_admin_siempre_ve_todas_aunque_tenga_asignaciones(): void
    {
        $tenantId = $this->seedTenant();
        $admin    = $this->actingAsUser($tenantId, 1);
        $authAdmin = $this->bearer($admin['token']);

        $e1 = (int) $this->postJson('/empresas', ['nombre' => 'Admin ve todo A'], $authAdmin)['json']['data']['id'];
        $this->postJson('/empresas', ['nombre' => 'Admin ve todo B'], $authAdmin);

        $this->putJson("/admin/users/{$admin['userId']}/empresas", ['empresa_ids' => [$e1]], $authAdmin);

        $lista = $this->getJson('/empresas', $authAdmin);
        $this->assertCount(2, $lista['json']['data']);
    }

    public function test_no_se_puede_asignar_una_empresa_de_otro_tenant(): void
    {
        $tenantA = $this->seedTenant();
        $tenantB = $this->seedTenant();
        $adminA  = $this->actingAsUser($tenantA, 1);
        $usuarioA = $this->actingAsUser($tenantA, 0);
        $adminB  = $this->actingAsUser($tenantB, 1);
        $authAdminA = $this->bearer($adminA['token']);
        $authAdminB = $this->bearer($adminB['token']);

        $empresaAjena = (int) $this->postJson('/empresas', ['nombre' => 'Empresa de B'], $authAdminB)
            ['json']['data']['id'];

        $put = $this->putJson(
            "/admin/users/{$usuarioA['userId']}/empresas",
            ['empresa_ids' => [$empresaAjena]],
            $authAdminA,
        );
        $this->assertSame(404, $put['status']);
    }
}
