<?php

/**
 * Siembra el catálogo global de actividades (AFIP/NAES) desde los datos reales del
 * sistema legacy (Visual IVA). Origen: `seeders/data/actividades.sql` (generado del
 * dump legacy, reordenando columnas a `actividades(id, nombre, codigo, descripcion_larga)`).
 *
 * Uso:  php seeders/ActividadesSeeder.php
 *
 * Idempotente: los INSERT son `INSERT IGNORE` (se saltean ids ya presentes). Se preservan
 * los ID_ACTIVIDAD legacy para que la migración de datos reales referencie por id.
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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Conserva ids legacy tal cual (por si alguno fuese 0).
$pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");

$ya = (int) $pdo->query('SELECT COUNT(*) FROM actividades')->fetchColumn();
if ($ya > 0) {
    echo "actividades: ya hay {$ya} filas, se omite.\n";
    return;
}

$file = __DIR__ . '/data/actividades.sql';
$fh = fopen($file, 'r');
if ($fh === false) {
    fwrite(STDERR, "No se pudo abrir {$file}\n");
    exit(1);
}

// Ejecuta cada sentencia (encabezado + filas hasta la línea que termina en ';').
$buffer = '';
$sentencias = 0;
$pdo->beginTransaction();
while (($linea = fgets($fh)) !== false) {
    $buffer .= $linea;
    if (preg_match('/;\s*$/', $linea)) {
        $pdo->exec($buffer);
        $buffer = '';
        $sentencias++;
    }
}
fclose($fh);
$pdo->commit();

$total = (int) $pdo->query('SELECT COUNT(*) FROM actividades')->fetchColumn();
echo "actividades sembradas: {$total} filas ({$sentencias} sentencias).\n";
