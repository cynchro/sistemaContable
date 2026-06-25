<?php

/**
 * Seeder: registra las claves de permiso (keys) del módulo IVA para que el estudio
 * pueda asignarlas a roles granulares vía /admin/roles/{id}/assign.
 *
 * El admin (rol con 'Acceso Total') NO necesita estas keys: el super-permiso ya
 * habilita todo (ver PermissionChecker). Son para roles acotados (p. ej. un rol que
 * sólo carga ventas, o que sólo lee el libro IVA).
 *
 * Idempotente. Uso: php seeders/PermisosIvaSeeder.php
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

// Una key por recurso; el nivel (1 lectura / 2 escritura) se fija al asignar el
// permiso al rol. `estado` en `permisos` es sólo el estado de la fila (activo).
$keys = [
    'iva.clientes',
    'iva.proveedores',
    'iva.ventas',
    'iva.compras',
    'iva.libro',
    'iva.padron',
    'iva.facturacion',
    'iva.puntos-venta',
    'iva.auditoria',
];

$select = $pdo->prepare('SELECT id FROM permisos WHERE `key` = ?');
$insert = $pdo->prepare('INSERT INTO permisos (`key`, estado) VALUES (?, 2)');

foreach ($keys as $key) {
    $select->execute([$key]);

    if ($select->fetch()) {
        echo "  [exists]  permiso: {$key}\n";
        continue;
    }

    $insert->execute([$key]);
    echo "  [created] permiso: {$key}\n";
}

echo "Listo. Permisos del módulo IVA sembrados.\n";
