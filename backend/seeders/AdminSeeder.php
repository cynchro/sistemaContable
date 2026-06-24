<?php

/**
 * Seeds the first administrator user.
 *
 * Usage:
 *   php seeders/AdminSeeder.php
 *
 * Reads DB credentials from .env in the project root.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

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

$username   = $argv[1] ?? 'admin@example.com';
$password   = $argv[2] ?? bin2hex(random_bytes(8));
$tenantName = $argv[3] ?? 'Default';

$stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = 1');
$stmt->execute();
if ($stmt->fetchColumn() > 0) {
    echo "An administrator already exists. Aborting.\n";
    exit(1);
}

// Generate UUID v4 in PHP to avoid relying on DB-generated value
$data    = random_bytes(16);
$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
$tenantId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

$stmt = $pdo->prepare('INSERT INTO tenants (id, nombre) VALUES (?, ?)');
$stmt->execute([$tenantId, $tenantName]);

$hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare('INSERT INTO usuarios (usuario, clave, rol, tenant_id) VALUES (?, ?, 1, ?)');
$stmt->execute([$username, $hashed, $tenantId]);

echo "Administrator created successfully.\n";
echo "  Email    : {$username}\n";
echo "  Password : {$password}\n";
echo "  Tenant   : {$tenantName} ({$tenantId})\n";
echo "Store these credentials safely — the password will not be shown again.\n";
