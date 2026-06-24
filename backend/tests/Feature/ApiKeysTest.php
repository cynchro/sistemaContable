<?php

namespace Tests\Feature;

/**
 * API keys de terceros end-to-end contra DB real: emisión/listado/revocación por
 * un usuario de la app (scope '*'), y la prevención de escalada de privilegios —
 * una API key sin el scope `apikeys.manage` no puede administrar keys (403).
 */
class ApiKeysTest extends FeatureTestCase
{
    public function test_user_can_issue_and_list_api_keys(): void
    {
        $ctx  = $this->actingAsUser();
        $auth = $this->bearer($ctx['token']);

        $created = $this->postJson('/api-keys', ['name' => 'CI key', 'scopes' => ['reports.read']], $auth);

        $this->assertSame(201, $created['status']);
        $this->assertStringStartsWith('mk_', $created['json']['data']['token']);

        $list = $this->getJson('/api-keys', $auth);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, $list['json']['data']);
        // El hash nunca se expone.
        $this->assertArrayNotHasKey('hash', $list['json']['data'][0]);
    }

    public function test_issued_key_authenticates_but_cannot_manage_keys_without_scope(): void
    {
        $ctx = $this->actingAsUser();

        // El usuario (scope '*') emite una key acotada a 'reports.read'.
        $created = $this->postJson(
            '/api-keys',
            ['name' => 'scoped', 'scopes' => ['reports.read']],
            $this->bearer($ctx['token'])
        );
        $keyToken = (string) $created['json']['data']['token'];

        // Esa key se autentica, pero ScopeMiddleware:apikeys.manage la frena → 403.
        $res = $this->getJson('/api-keys', $this->bearer($keyToken));

        $this->assertSame(403, $res['status']);
    }

    public function test_user_can_revoke_a_key(): void
    {
        $ctx  = $this->actingAsUser();
        $auth = $this->bearer($ctx['token']);

        $created = $this->postJson('/api-keys', ['name' => 'to-revoke'], $auth);
        $id      = (string) $created['json']['data']['key']['id'];

        $revoked = $this->deleteJson("/api-keys/{$id}", $auth);
        $this->assertSame(200, $revoked['status']);
    }

    public function test_requires_authentication(): void
    {
        $res = $this->getJson('/api-keys');

        $this->assertSame(401, $res['status']);
    }
}
