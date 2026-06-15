<?php

namespace App\Modules\Auth\Controllers;

use App\Support\Config;
use App\Support\Request;
use App\Support\Response;
use App\Modules\Auth\Requests\AuthRequest;
use App\Modules\Auth\Services\AuthService;

class AuthController
{
    public function __construct(private AuthService $service)
    {
    }

    public function register(AuthRequest $request): Response
    {
        $result = $this->service->register($request->all());
        return Response::success($result, 201);
    }

    public function login(AuthRequest $request): Response
    {
        $tokens = $this->service->login($request->all());
        return Response::success($tokens);
    }

    public function refresh(Request $request): Response
    {
        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            return Response::error('refresh_token is required.', 400);
        }

        $tokens = $this->service->refreshTokens((string) $refreshToken);
        return Response::success($tokens);
    }

    public function logout(Request $request): Response
    {
        $accessToken  = (string) $request->bearerToken();
        $refreshToken = $request->input('refresh_token');

        $this->service->logout($accessToken, $refreshToken ? (string) $refreshToken : null);
        return Response::success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): Response
    {
        $user = $this->service->me((string) $request->bearerToken());
        return Response::success(['user' => $user]);
    }

    public function permisos(Request $request): Response
    {
        $key      = (string) $request->route('key', '');
        $permisos = $this->service->permisos((string) $request->bearerToken(), $key);
        return Response::success($permisos);
    }

    public function impersonate(Request $request): Response
    {
        $user          = $request->user();
        $adminId       = (int) ($user['sub'] ?? 0);
        $targetId      = (int) $request->input('targetUserId');
        $adminTenantId = $request->tenantId();
        $token         = $this->service->impersonate($adminId, $targetId, $adminTenantId);

        return Response::success([
            'token'       => $token,
            'redirectUrl' => Config::get('app.impersonate_url', ''),
        ]);
    }
}
