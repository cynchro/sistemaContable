<?php

namespace Tests\Feature;

use PDO;
use PHPUnit\Framework\TestCase;
use App\Support\Config;
use App\Support\Container;
use App\Support\Router;
use App\Support\Pipeline;
use App\Support\Request;
use App\Support\Request as AppRequest;
use App\Support\JWTConfig;
use App\Exceptions\AppException;
use App\Exceptions\QuotaExceededException;

/**
 * Base de los Feature/integration tests: HTTP real contra una base MySQL real.
 *
 * - La conexión PDO se comparte entre el test y la app (mismo singleton del
 *   container) para que cada test corra dentro de una transacción que se
 *   revierte en tearDown → aislamiento sin re-migrar entre tests.
 * - El esquema se crea una sola vez por proceso (drop-all + migraciones), así
 *   que el estado es determinista sin importar corridas previas.
 * - `request()` despacha por el Router real (los middlewares de ruta —Auth,
 *   Tenant, Scope, Entitlement, Quota— sí se ejecutan) y mapea las AppException
 *   a status/headers igual que el Handler de producción.
 *
 * Requiere una base alcanzable según config/database.php (phpunit.xml fija las
 * env DB_*). En CI la provee el service mysql:8.0.
 */
abstract class FeatureTestCase extends TestCase
{
    protected Container $app;
    protected PDO $pdo;

    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        Config::setPath(dirname(__DIR__, 2) . '/config');

        // Los Feature tests requieren MySQL real. Si no está disponible (sin
        // pdo_mysql, o sin DB alcanzable — p. ej. en el pre-push local), se
        // saltan en vez de fallar. El CI los corre contra su service mysql:8.0.
        try {
            $this->pdo = $this->makePdo();
        } catch (\PDOException $e) {
            $this->markTestSkipped('MySQL no disponible para Feature tests: ' . $e->getMessage());
        }

        if (!self::$schemaReady) {
            $this->migrateFresh($this->pdo);
            self::$schemaReady = true;
        }

        $this->app = new Container();
        $this->bindServices($this->app, $this->pdo);
        $this->loadModuleRoutes($this->app);

        // Cada test corre en una transacción que se revierte al final.
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        AppRequest::setTestInputStream(null);
        $_SERVER = [];
        $_POST   = [];
        $_GET    = [];

        parent::tearDown();
    }

    // ── Infraestructura ───────────────────────────────────────────────────────

    private function makePdo(): PDO
    {
        $cfg = Config::all('database');
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

        return new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
    }

    /** Drop-all + corre todas las migraciones en orden (esquema limpio y determinista). */
    private function migrateFresh(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = (array) $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        foreach (glob(dirname(__DIR__, 2) . '/migrations/[0-9]*.php') ?: [] as $file) {
            $migration = require $file;
            $migration->up($pdo);
        }
    }

    private function bindServices(Container $app, PDO $pdo): void
    {
        $app->instance(PDO::class, $pdo);

        $app->singleton(\App\Support\Logger::class, fn () =>
            new \App\Support\Logger(Config::all('logging')));

        $app->singleton(\App\Support\DB::class, fn ($c) =>
            new \App\Support\DB($c->get(PDO::class)));

        // ArrayCache: operativo y sin depender de la extensión APCu.
        $app->singleton(\App\Support\Contracts\CacheInterface::class, fn () =>
            new \App\Support\Cache\ArrayCache());

        $app->singleton(\App\Support\Contracts\EntitlementResolverInterface::class, fn ($c) =>
            new \App\Support\Entitlements\DbEntitlementResolver($c->get(PDO::class)));

        $app->singleton(\App\Support\Contracts\UsageRecorderInterface::class, fn ($c) =>
            new \App\Support\Usage\DbUsageRecorder($c->get(PDO::class)));

        $app->singleton(\App\Support\Contracts\WebhookVerifierInterface::class, fn ($c) =>
            new \App\Support\Webhook\WebhookVerifier(
                $c->get(\App\Support\Contracts\CacheInterface::class)
            ));

        $app->singleton(\App\Support\EventDispatcher::class, fn () =>
            new \App\Support\EventDispatcher());

        $app->singleton(Router::class, fn ($c) => new Router($c));
    }

    private function loadModuleRoutes(Container $app): void
    {
        /** @var Router $router */
        $router = $app->get(Router::class);
        foreach (glob(dirname(__DIR__, 2) . '/app/Modules/*/routes.php') ?: [] as $file) {
            require $file;
        }
    }

    // ── Cliente HTTP de test ──────────────────────────────────────────────────

    /**
     * Despacha una petición y devuelve el resultado normalizado:
     *   ['status' => int, 'json' => array, 'headers' => array<string,string>]
     *
     * @param  array<string, mixed> $body
     * @param  array<string, string> $headers
     * @return array{status: int, json: array<string, mixed>, headers: array<string, string>}
     */
    protected function request(string $method, string $uri, array $body = [], array $headers = []): array
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI']    = $uri;
        $_SERVER['CONTENT_TYPE']   = 'application/json';

        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        AppRequest::setTestInputStream($body === [] ? '' : (string) json_encode($body));

        /** @var Router $router */
        $router  = $this->app->get(Router::class);
        $request = new Request();

        try {
            $response = $router->dispatch($request, new Pipeline());

            ob_start();
            $response->send();
            $output = (string) ob_get_clean();

            return [
                'status'  => $response->getStatus(),
                'json'    => json_decode($output, true) ?? [],
                'headers' => $response->getHeaders(),
            ];
        } catch (AppException $e) {
            $headers = [];
            if ($e instanceof QuotaExceededException && $e->getRetryAfter() !== null) {
                $headers['Retry-After'] = (string) $e->getRetryAfter();
            }

            return [
                'status'  => $e->getHttpStatusCode(),
                'json'    => $e->toArray(),
                'headers' => $headers,
            ];
        }
    }

    /** @param array<string, string> $headers @return array{status:int,json:array<string,mixed>,headers:array<string,string>} */
    protected function getJson(string $uri, array $headers = []): array
    {
        return $this->request('GET', $uri, [], $headers);
    }

    /** @param array<string,mixed> $body @param array<string,string> $headers @return array{status:int,json:array<string,mixed>,headers:array<string,string>} */
    protected function postJson(string $uri, array $body = [], array $headers = []): array
    {
        return $this->request('POST', $uri, $body, $headers);
    }

    /** @param array<string,mixed> $body @param array<string,string> $headers @return array{status:int,json:array<string,mixed>,headers:array<string,string>} */
    protected function putJson(string $uri, array $body = [], array $headers = []): array
    {
        return $this->request('PUT', $uri, $body, $headers);
    }

    /** @param array<string,string> $headers @return array{status:int,json:array<string,mixed>,headers:array<string,string>} */
    protected function deleteJson(string $uri, array $headers = []): array
    {
        return $this->request('DELETE', $uri, [], $headers);
    }

    protected function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    // ── Seeders / helpers de dominio ──────────────────────────────────────────

    protected function seedTenant(string $nombre = 'Acme'): string
    {
        $id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );

        $this->pdo->prepare('INSERT INTO tenants (id, nombre) VALUES (?, ?)')
            ->execute([$id, $nombre]);

        return $id;
    }

    /**
     * Inserta un tenant (si no se pasa) y un usuario, genera su JWT y lo guarda en
     * `usuarios.token` (lo que exige JwtGuard para la revocación). Devuelve el
     * contexto para autenticar peticiones.
     *
     * @return array{tenantId: string, userId: int, token: string}
     */
    protected function actingAsUser(?string $tenantId = null, int $rol = 1): array
    {
        $tenantId ??= $this->seedTenant();

        $this->pdo->prepare(
            'INSERT INTO usuarios (usuario, clave, rol, tenant_id) VALUES (?, ?, ?, ?)'
        )->execute([
            'user_' . bin2hex(random_bytes(4)),
            password_hash('secret-password', PASSWORD_BCRYPT),
            $rol,
            $tenantId,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $token  = JWTConfig::generateToken($userId, $tenantId);

        $this->pdo->prepare('UPDATE usuarios SET token = ? WHERE id = ?')
            ->execute([$token, $userId]);

        return ['tenantId' => $tenantId, 'userId' => $userId, 'token' => $token];
    }

    /** Habilita una feature (flag) para el tenant. */
    protected function grantFlag(string $tenantId, string $feature): void
    {
        $this->pdo->prepare(
            "INSERT INTO tenant_entitlements (tenant_id, feature, type, enabled, source)
             VALUES (?, ?, 'flag', 1, 'manual')"
        )->execute([$tenantId, $feature]);
    }

    /** Habilita una cuota (quota) con límite y ciclo vigente para el tenant. */
    protected function grantQuota(string $tenantId, string $feature, int $limit): void
    {
        $this->pdo->prepare(
            "INSERT INTO tenant_entitlements
                (tenant_id, feature, type, limit_value, enabled, source, period_start, period_end)
             VALUES (?, ?, 'quota', ?, 1, 'manual', NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 29 DAY)"
        )->execute([$tenantId, $feature, $limit]);
    }

    /** Registra consumo de uso para el tenant/feature. */
    protected function recordUsage(string $tenantId, string $feature, int $quantity = 1): void
    {
        $this->pdo->prepare(
            'INSERT INTO usage_events (tenant_id, metric, quantity) VALUES (?, ?, ?)'
        )->execute([$tenantId, $feature, $quantity]);
    }

    /**
     * Registra una ruta ad-hoc para tests (p. ej. para ejercitar middlewares de
     * gating sin depender de que un módulo las exponga).
     *
     * @param list<string> $middlewares
     */
    protected function registerRoute(string $method, string $uri, array $action, array $middlewares = []): void
    {
        /** @var Router $router */
        $router = $this->app->get(Router::class);
        $router->{strtolower($method)}($uri, $action, $middlewares);
    }
}
