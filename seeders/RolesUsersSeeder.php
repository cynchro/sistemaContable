<?php

/**
 * Seeder: creates default tenant, admin role, permission, and admin user.
 *
 * Usage: php seeders/RolesUsersSeeder.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'],
        $_ENV['DB_PORT'] ?? 3306,
        $_ENV['DB_NAME']
    ),
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ── Tenant ────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM tenants WHERE nombre = ?');
$stmt->execute(['Main Tenant']);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    // Generate UUID in PHP — DEFAULT (UUID()) is not returned by lastInsertId()
    $tenantId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $stmt = $pdo->prepare('INSERT INTO tenants (id, nombre) VALUES (?, ?)');
    $stmt->execute([$tenantId, 'Main Tenant']);
    echo "  [created] tenant: Main Tenant ({$tenantId})\n";
} else {
    $tenantId = $tenant['id'];
    echo "  [exists]  tenant: Main Tenant ({$tenantId})\n";
}

// ── Role ──────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id FROM roles WHERE nombre = 'Administrador'");
$stmt->execute();
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    $pdo->prepare("INSERT INTO roles (nombre, estado) VALUES ('Administrador', 'activo')")->execute();
    $roleId = (int) $pdo->lastInsertId();
    echo "  [created] role: Administrador (id={$roleId})\n";
} else {
    $roleId = (int) $role['id'];
    echo "  [exists]  role: Administrador (id={$roleId})\n";
}

// ── Permission ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id FROM permisos WHERE `key` = 'Acceso Total'");
$stmt->execute();
$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    $pdo->prepare("INSERT INTO permisos (`key`, estado) VALUES ('Acceso Total', 2)")->execute();
    $permisoId = (int) $pdo->lastInsertId();
    echo "  [created] permiso: Acceso Total (id={$permisoId})\n";
} else {
    $permisoId = (int) $permiso['id'];
    echo "  [exists]  permiso: Acceso Total (id={$permisoId})\n";
}

// ── Role → Permission ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM roles_permisos WHERE rol = ? AND permiso = ?');
$stmt->execute([$roleId, $permisoId]);

if (!$stmt->fetch()) {
    $pdo->prepare('INSERT INTO roles_permisos (rol, permiso, estado) VALUES (?, ?, 2)')
        ->execute([$roleId, $permisoId]);
    echo "  [created] roles_permisos: Administrador → Acceso Total\n";
} else {
    echo "  [exists]  roles_permisos: Administrador → Acceso Total\n";
}

// ── Admin user ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = 'admin@admin.com'");
$stmt->execute();

if (!$stmt->fetch()) {
    $pdo->prepare('INSERT INTO usuarios (usuario, clave, rol, tenant_id) VALUES (?, ?, ?, ?)')
        ->execute(['admin@admin.com', password_hash('admin123', PASSWORD_BCRYPT), $roleId, $tenantId]);
    echo "  [created] user: admin@admin.com / admin123\n";
} else {
    echo "  [exists]  user: admin@admin.com\n";
}

echo "\nSeeder complete.\n";
