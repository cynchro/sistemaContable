<?php

namespace Tests\Feature;

/**
 * RBAC por recurso en el módulo IVA (§B): las rutas exigen el permiso correspondiente
 * vía PermissionMiddleware. Un rol sin el permiso recibe 403; el nivel lectura no
 * habilita escritura. El admin (rol 1 con 'Acceso Total') pasa todo — lo cubren los
 * demás tests Feature, que usan actingAsUser() por defecto.
 */
class IvaRbacTest extends FeatureTestCase
{
    private function crearRolConPermiso(int $rolId, string $key, int $estado): void
    {
        $this->pdo->exec("INSERT IGNORE INTO roles (id, nombre, estado) VALUES ({$rolId}, 'Rol{$rolId}', 'activo')");
        $this->pdo->exec("INSERT IGNORE INTO permisos (`key`, estado) VALUES ('{$key}', {$estado})");
        $permisoId = (int) $this->pdo->query("SELECT id FROM permisos WHERE `key` = '{$key}'")->fetchColumn();
        $this->pdo->prepare('INSERT INTO roles_permisos (rol, permiso, estado) VALUES (?, ?, ?)')
            ->execute([$rolId, $permisoId, $estado]);
    }

    public function test_rol_sin_permiso_recibe_403(): void
    {
        // Rol 77 sin ningún permiso asignado.
        $auth = $this->bearer($this->actingAsUser(null, 77)['token']);

        $this->assertSame(403, $this->getJson('/empresas/1/clientes', $auth)['status']);
        $this->assertSame(403, $this->getJson('/empresas/1/periodos/1/ventas', $auth)['status']);
    }

    public function test_lectura_no_habilita_escritura(): void
    {
        // Rol con 'iva.clientes' en nivel lectura (estado 1).
        $this->crearRolConPermiso(50, 'iva.clientes', 1);
        $auth = $this->bearer($this->actingAsUser(null, 50)['token']);

        // El POST exige escritura → 403 por permiso.
        $this->assertSame(403, $this->postJson('/empresas/1/clientes', ['nombre' => 'X'], $auth)['status']);

        // El GET exige sólo lectura → el permiso pasa (no 403). La empresa inexistente
        // del tenant da 404, lo que confirma que NO fue cortado por el middleware.
        $this->assertSame(404, $this->getJson('/empresas/1/clientes', $auth)['status']);
    }
}
