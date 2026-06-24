<?php

namespace Tests\Feature;

/**
 * Flujo de autenticación end-to-end contra DB real: login (verifica credenciales
 * + emite JWT), petición autenticada (JwtGuard valida el token en DB) y rechazo
 * sin credenciales.
 */
class AuthFlowTest extends FeatureTestCase
{
    public function test_login_with_valid_credentials_returns_tokens(): void
    {
        $tenantId = $this->seedTenant();
        $this->pdo->prepare(
            'INSERT INTO usuarios (usuario, clave, rol, tenant_id) VALUES (?, ?, ?, ?)'
        )->execute(['juan@example.com', password_hash('secret-password', PASSWORD_BCRYPT), 1, $tenantId]);

        $res = $this->postJson('/auth/login', ['usuario' => 'juan@example.com', 'clave' => 'secret-password']);

        $this->assertSame(200, $res['status']);
        $this->assertArrayHasKey('access_token', $res['json']['data']);
        $this->assertNotEmpty($res['json']['data']['access_token']);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $tenantId = $this->seedTenant();
        $this->pdo->prepare(
            'INSERT INTO usuarios (usuario, clave, rol, tenant_id) VALUES (?, ?, ?, ?)'
        )->execute(['juan@example.com', password_hash('secret-password', PASSWORD_BCRYPT), 1, $tenantId]);

        $res = $this->postJson('/auth/login', ['usuario' => 'juan@example.com', 'clave' => 'wrongpassword']);

        $this->assertSame(401, $res['status']);
    }

    public function test_authenticated_request_succeeds_with_valid_token(): void
    {
        $ctx = $this->actingAsUser();

        $res = $this->postJson('/auth/me', [], $this->bearer($ctx['token']));

        $this->assertSame(200, $res['status']);
        $this->assertSame($ctx['userId'], (int) $res['json']['data']['user']['id']);
    }

    public function test_request_without_token_is_rejected(): void
    {
        $res = $this->postJson('/auth/me');

        $this->assertSame(401, $res['status']);
    }

    public function test_revoked_token_is_rejected(): void
    {
        $ctx = $this->actingAsUser();
        // Simula logout/revocación: el token deja de estar en usuarios.token.
        $this->pdo->prepare('UPDATE usuarios SET token = NULL WHERE id = ?')->execute([$ctx['userId']]);

        $res = $this->postJson('/auth/me', [], $this->bearer($ctx['token']));

        $this->assertSame(401, $res['status']);
    }
}
