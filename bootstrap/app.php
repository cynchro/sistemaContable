<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ── Stage 1: Environment ──────────────────────────────────────────────────────
$projectRoot = dirname(__DIR__);
$envPath     = $projectRoot . '/.env';

$dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
$dotenv->safeLoad();

if (!is_file($envPath)) {
    if (\PHP_SAPI === 'cli') {
        fwrite(STDERR, ".env no encontrado. Copia .env.example a .env y configura las variables.\n");
        exit(1);
    }
    App\Support\Response::error('.env no encontrado', 503)->send();
    exit;
}

$dotenv->required(['JWT_SECRET', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

// ── Stage 2: Config ───────────────────────────────────────────────────────────
App\Support\Config::setPath(dirname(__DIR__) . '/config');

// ── Stage 3: Error display ────────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ── Stage 4: Container ────────────────────────────────────────────────────────
$app = new App\Support\Container();

// ── Stage 5: Logger & exception handler ──────────────────────────────────────
$app->singleton(App\Support\Logger::class, fn () =>
    new App\Support\Logger(App\Support\Config::all('logging')));

App\Exceptions\Handler::register($app->get(App\Support\Logger::class));

// ── Stage 6: Database ─────────────────────────────────────────────────────────
$app->singleton(\PDO::class, function (): \PDO {
    $cfg = App\Support\Config::all('database');
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
    return new \PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
});

$app->singleton(App\Support\DB::class, fn ($c) =>
    new App\Support\DB($c->get(\PDO::class)));

$app->singleton(App\Support\JobDispatcher::class, fn ($c) =>
    new App\Support\JobDispatcher($c->get(\PDO::class)));

$app->singleton(
    App\Support\Contracts\CacheInterface::class,
    function ($c) {
        $cache = new App\Support\Cache\ApcuCache();
        if (!$cache->available()) {
            // El cache compartido es prerequisito de la protección anti-replay
            // de webhooks (WebhookVerifier falla cerrado sin él). Avisamos fuerte
            // para que no pase inadvertido en producción.
            $c->get(App\Support\Logger::class)->warning(
                'CacheInterface resolved to APCu but it is not enabled '
                . '(apcu_enabled() === false). Webhook anti-replay will reject '
                . 'all signatures until a working shared cache is configured.'
            );
        }
        return $cache;
    }
);

$app->singleton(
    App\Support\Contracts\WebhookVerifierInterface::class,
    fn ($c) => new App\Support\Webhook\WebhookVerifier(
        $c->get(App\Support\Contracts\CacheInterface::class)
    )
);

$app->singleton(
    App\Support\Contracts\EntitlementResolverInterface::class,
    fn ($c) => new App\Support\Entitlements\DbEntitlementResolver(
        $c->get(\PDO::class)
    )
);

$app->singleton(
    App\Support\Contracts\UsageRecorderInterface::class,
    fn ($c) => new App\Support\Usage\DbUsageRecorder(
        $c->get(\PDO::class)
    )
);

// ── Stage 7: Router & Kernel ──────────────────────────────────────────────────
$app->singleton(App\Support\Router::class, fn ($c) =>
    new App\Support\Router($c));

$app->singleton(App\Support\Kernel::class, fn ($c) =>
    new App\Support\Kernel($c));

// ── Stage 7.5: Event dispatcher & module service providers ───────────────────
$app->singleton(App\Support\EventDispatcher::class, fn () =>
    new App\Support\EventDispatcher());

foreach (glob(__DIR__ . '/../app/Modules/*/ServiceProvider.php') as $spFile) {
    if (preg_match('/Modules\/([^\/]+)\/ServiceProvider\.php$/', $spFile, $m)) {
        $spClass = "App\\Modules\\{$m[1]}\\ServiceProvider";
        if (class_exists($spClass)) {
            $provider = new $spClass($app);
            $provider->register();
            $provider->boot();
        }
    }
}

// ── Stage 8: Module routes (auto-discovered) ──────────────────────────────────
$router = $app->get(App\Support\Router::class);
foreach (glob(__DIR__ . '/../app/Modules/*/routes.php') as $routesFile) {
    require $routesFile;
}

// ── Stage 9: Infrastructure routes ───────────────────────────────────────────
$router->get('/', [App\Http\Controllers\HomeController::class, 'index']);
$router->get('/health', [App\Http\Controllers\HealthController::class, 'check']);

$router->group(
    [App\Http\Middleware\AuthMiddleware::class, App\Http\Middleware\AdminMiddleware::class],
    function ($router) {
        $router->get('/admin/logs', [App\Http\Controllers\LogsController::class, 'index']);
        $router->get('/admin/logs/{id}', [App\Http\Controllers\LogsController::class, 'show']);
        $router->delete('/admin/logs', [App\Http\Controllers\LogsController::class, 'deleteAll']);
    }
);

return $app;
