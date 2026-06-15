<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Support\Request;

abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $_POST   = [];
        $_GET    = [];
        $_FILES  = [];
    }

    /** @param array<string, mixed>|null $user */
    protected function makeRequest(?array $user = null, ?string $tenantId = null): Request
    {
        $request = new Request();
        if ($user !== null) {
            $request->setUser($user);
        }
        if ($tenantId !== null) {
            $request->setTenantId($tenantId);
        }
        return $request;
    }
}
