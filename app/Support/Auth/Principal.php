<?php

namespace App\Support\Auth;

/**
 * Identidad resuelta por un guard, unificada para cualquier esquema de
 * autenticación (usuario vía JWT o credencial de tercero vía API key).
 */
final class Principal
{
    /**
     * @param 'user'|'api_key'      $type
     * @param list<string>          $scopes Scopes concedidos ('*' = todos).
     * @param array<string, mixed>  $claims Datos crudos del esquema (payload JWT
     *                                      o fila de api_keys). Preserva la
     *                                      retrocompatibilidad con Request::user().
     */
    public function __construct(
        public readonly string $type,
        public readonly string $tenantId,
        public readonly ?int $userId = null,
        public readonly array $scopes = [],
        public readonly ?int $rol = null,
        public readonly array $claims = []
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return in_array('*', $this->scopes, true)
            || in_array($scope, $this->scopes, true);
    }

    public function isUser(): bool
    {
        return $this->type === 'user';
    }

    public function isApiKey(): bool
    {
        return $this->type === 'api_key';
    }
}
