<?php

/**
 * Seeds billing plans and their entitlements (idempotent).
 *
 * Requires the optional billing module:
 *   composer require cynchro/modux-billing
 *
 * Usage:
 *   php seeders/BillingPlansSeeder.php
 *
 * Edit the $plans array below to match your product. Re-running updates the
 * entitlements of existing plans (upsert) without duplicating.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Cynchro\Billing\Schema;
use Cynchro\Billing\PlanRepository;
use Cynchro\Billing\Models\PlanEntitlement;

if (!class_exists(Schema::class)) {
    fwrite(STDERR, "Billing no está instalado. Ejecutá: composer require cynchro/modux-billing\n");
    exit(1);
}

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'] ?? '3306',
    $_ENV['DB_NAME'],
);

$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$gateway = $_ENV['BILLING_GATEWAY'] ?? 'stripe';

// ── Definí tus planes acá ──────────────────────────────────────────────────────
// type: 'flag' (tiene/no) | 'quota' (límite por ciclo) | 'seat' (asientos).
// limit null/ausente = ilimitado. feature namespaced (ia.rag, bots.outbound, ...).
$plans = [
    [
        'key' => 'starter', 'name' => 'Starter', 'price' => 0.0, 'interval' => 'month',
        'entitlements' => [
            ['feature' => 'seats',     'type' => 'seat',  'limit' => 1],
            ['feature' => 'api.calls', 'type' => 'quota', 'limit' => 1000],
        ],
    ],
    [
        'key' => 'pro', 'name' => 'Pro', 'price' => 29.0, 'interval' => 'month',
        'entitlements' => [
            ['feature' => 'seats',         'type' => 'seat',  'limit' => 5],
            ['feature' => 'api.calls',     'type' => 'quota', 'limit' => 50000],
            ['feature' => 'ia.rag',        'type' => 'flag'],
            ['feature' => 'bots.outbound', 'type' => 'flag'],
        ],
    ],
];
// ───────────────────────────────────────────────────────────────────────────────

Schema::up($pdo);

$repo = new PlanRepository($pdo);

foreach ($plans as $def) {
    $existing = $repo->findByKey($def['key']);
    $plan     = $existing ?? $repo->create(
        $def['key'],
        $def['name'],
        $def['price'],
        'USD',
        $def['interval'],
        $gateway
    );

    foreach ($def['entitlements'] as $e) {
        $repo->addEntitlement($plan->id, new PlanEntitlement($e['feature'], $e['type'], $e['limit'] ?? null));
    }

    $verb     = $existing ? 'updated' : 'created';
    $features = implode(', ', array_column($def['entitlements'], 'feature'));
    echo "  {$verb}  {$def['key']}  ({$features})\n";
}

echo "Billing plans seeded.\n";
