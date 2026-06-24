<?php

namespace App\Modules\ApiKeys\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\ApiKeys\Services\ApiKeyService;
use App\Modules\ApiKeys\Requests\CreateApiKeyRequest;

class ApiKeyController
{
    public function __construct(private ApiKeyService $service)
    {
    }

    public function index(Request $request): Response
    {
        $tenantId = (string) $request->tenantId();
        return Response::success($this->service->list($tenantId));
    }

    public function show(Request $request): Response
    {
        $id       = (string) $request->route('id');
        $tenantId = (string) $request->tenantId();
        return Response::success($this->service->get($id, $tenantId));
    }

    public function create(Request $request, CreateApiKeyRequest $validated): Response
    {
        $tenantId = (string) $request->tenantId();
        $name     = (string) $validated->input('name');
        $scopes   = (array) $validated->input('scopes', []);

        $result = $this->service->create($tenantId, $name, $scopes);

        return Response::success($result, 201);
    }

    public function revoke(Request $request): Response
    {
        $id       = (string) $request->route('id');
        $tenantId = (string) $request->tenantId();

        $this->service->revoke($id, $tenantId);

        return Response::success(['message' => 'API key revoked.']);
    }
}
