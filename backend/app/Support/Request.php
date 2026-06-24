<?php

namespace App\Support;

use App\Exceptions\ValidationException;

class Request
{
    private array $post;
    private array $get;
    private array $files;
    private array $server;
    private array $jsonData;
    private string $rawBody;
    private array $routeParams = [];
    private ?array $resolvedUser = null;
    private ?string $resolvedTenantId = null;
    private ?\App\Support\Auth\Principal $resolvedPrincipal = null;

    private static ?string $testInputStream = null;

    public static function setTestInputStream(?string $body): void
    {
        self::$testInputStream = $body;
    }

    public function __construct()
    {
        $this->post     = $_POST;
        $this->get      = $_GET;
        $this->files    = $_FILES;
        $this->server   = $_SERVER;
        // El cuerpo crudo se captura una sola vez (php://input no siempre es
        // re-leíble) y se reutiliza para JSON y para verificación de firmas (webhooks).
        $this->rawBody  = self::$testInputStream ?? (string) file_get_contents('php://input');
        $this->jsonData = $this->parseJson();
    }

    private function parseJson(): array
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
            return [];
        }

        if ($this->rawBody === '') {
            return [];
        }

        try {
            return json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ValidationException(['body' => ['Invalid JSON payload.']]);
        }
    }

    /** Cuerpo crudo de la petición, tal cual llegó (sin parsear). */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key]
            ?? $this->jsonData[$key]
            ?? $this->post[$key]
            ?? $this->get[$key]
            ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->jsonData, $this->routeParams);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$normalized] ?? $this->server[$key] ?? $default;
    }

    public function uri(): string
    {
        return parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }

    public function ip(): string
    {
        $remoteAddr    = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
        $trustedProxies = Config::get('app.trusted_proxies', []);

        if (
            !empty($trustedProxies)
            && in_array($remoteAddr, $trustedProxies, true)
            && !empty($this->server['HTTP_X_FORWARDED_FOR'])
        ) {
            return trim(explode(',', $this->server['HTTP_X_FORWARDED_FOR'])[0]);
        }

        return $remoteAddr;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setUser(array $user): void
    {
        $this->resolvedUser = $user;
    }

    public function user(): ?array
    {
        return $this->resolvedUser;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->resolvedTenantId = $tenantId;
    }

    public function tenantId(): ?string
    {
        return $this->resolvedTenantId;
    }

    public function setPrincipal(\App\Support\Auth\Principal $principal): void
    {
        $this->resolvedPrincipal = $principal;
    }

    public function principal(): ?\App\Support\Auth\Principal
    {
        return $this->resolvedPrincipal;
    }

    /** @param list<string> $keys
     *  @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /** @param list<string> $keys
     *  @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function isJson(): bool
    {
        return !empty($this->jsonData);
    }

    public function __get(string $key): mixed
    {
        return $this->input($key);
    }
}
