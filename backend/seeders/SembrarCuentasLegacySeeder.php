<?php

/**
 * Siembra el plan de cuentas real del legacy Visual IVA — Etapa 1 del satélite
 * (depuración inicial del padrón, documento-1.md §9). Origen: exportación de
 * `EMPRESA` y `CUENTAS` de la base de producción real (636 MB,
 * `~/Descargas/VISUALIVA - copia (7).fdb`), normalizada y deduplicada a mano
 * (ver documentacion/refinamiento/ — 11.307 cuentas reales colapsan a 117
 * conceptos canónicos tras quitar diferencias de mayúsculas/acentos/espacios/
 * tipeo; ~29 colisiones resueltas por código de cuenta más frecuente).
 *
 * Uso:
 *   php seeders/SembrarCuentasLegacySeeder.php
 *
 * Archivos de entrada (seeders/data/):
 *  - empresas_legacy.csv: empresa_id_legacy;cuit;nombre;inactiva (345 filas,
 *    datos reales — gitignoreado, nunca versionado).
 *  - crosswalk_cuenta_concepto.csv: cuenta_id_legacy;empresa_id_legacy;
 *    codigo_legacy;nombre_legacy;concepto_canonico;preferida (11.307 filas,
 *    solo nombres de cuenta — sin CUIT/PII, sí versionado).
 *
 * Qué siembra, en orden:
 *  1. `empresas`: upsert por CUIT (no duplica si ya existe una empresa con ese
 *     CUIT en el tenant — p. ej. si ya se sincronizó desde SIGE).
 *  2. `iva_conceptos`: los 117 conceptos canónicos (tenant-wide).
 *  3. `cuentas`: una fila por cada cuenta legacy, en su empresa real (todas,
 *     incluidas las "no preferidas" de una colisión — no se pierde ninguna,
 *     por si algún comprobante histórico ya la referencia).
 *  4. `empresa_concepto_cuenta`: solo la cuenta "preferida" de cada
 *     (empresa, concepto) — la que queda como default de imputación.
 *
 * Idempotente: se puede correr de nuevo sin duplicar (upsert por CUIT en
 * empresas, `INSERT IGNORE`/lookup por nombre único en conceptos, y
 * verificación previa en cuentas/empresa_concepto_cuenta).
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

$dataDir = __DIR__ . '/data';
$empresasPath = $dataDir . '/empresas_legacy.csv';
$crosswalkPath = $dataDir . '/crosswalk_cuenta_concepto.csv';

if (!is_file($empresasPath)) {
    fwrite(STDERR, "No se encontró {$empresasPath} (dato real, gitignoreado — pedirlo aparte).\n");
    exit(1);
}
if (!is_file($crosswalkPath)) {
    fwrite(STDERR, "No se encontró {$crosswalkPath}.\n");
    exit(1);
}

/** @return list<list<string>> */
function leerCsv(string $path): array
{
    $rows = [];
    $fh = fopen($path, 'r');
    if ($fh === false) {
        throw new RuntimeException("No se pudo abrir {$path}");
    }
    $header = fgetcsv($fh, 0, ';');
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if ($row === [null] || $row === false) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

$tenantId = (string) $pdo->query('SELECT id FROM tenants ORDER BY id LIMIT 1')->fetchColumn();
if ($tenantId === '') {
    fwrite(STDERR, "No hay ningún tenant en la base — sembrar uno primero (AdminSeeder).\n");
    exit(1);
}
echo "Tenant: {$tenantId}\n";

$pdo->beginTransaction();

try {
    // ── 1. Empresas: upsert por CUIT ────────────────────────────────────────
    $empresasLegacy = leerCsv($empresasPath);
    echo 'Empresas legacy leídas: ' . count($empresasLegacy) . "\n";

    $findEmpresaByCuit = $pdo->prepare('SELECT id FROM empresas WHERE tenant_id = ? AND cuit = ?');
    $insertEmpresa = $pdo->prepare(
        'INSERT INTO empresas (tenant_id, cuit, nombre) VALUES (:tenant_id, :cuit, :nombre)'
    );

    /** @var array<int, int> $empresaIdPorLegacy legacy EMPRESA_ID => empresas.id nuevo */
    $empresaIdPorLegacy = [];
    $empresasCreadas = 0;
    $empresasReusadas = 0;

    foreach ($empresasLegacy as [$empresaIdLegacy, $cuit, $nombre, $inactiva]) {
        $cuit = preg_replace('/\D+/', '', $cuit) ?? '';
        if (strlen($cuit) !== 11) {
            continue; // guard, no debería pasar (se verificó 100% con CUIT completo)
        }

        $findEmpresaByCuit->execute([$tenantId, $cuit]);
        $existingId = $findEmpresaByCuit->fetchColumn();

        if ($existingId !== false) {
            $empresaIdPorLegacy[(int) $empresaIdLegacy] = (int) $existingId;
            $empresasReusadas++;
            continue;
        }

        $insertEmpresa->execute([
            'tenant_id' => $tenantId,
            'cuit'      => $cuit,
            'nombre'    => trim($nombre) !== '' ? trim($nombre) : "Empresa {$cuit}",
        ]);
        $empresaIdPorLegacy[(int) $empresaIdLegacy] = (int) $pdo->lastInsertId();
        $empresasCreadas++;
    }
    echo "  + empresas nuevas: {$empresasCreadas}, ya existían (por CUIT): {$empresasReusadas}\n";

    // ── 2. Conceptos canónicos ──────────────────────────────────────────────
    $crosswalk = leerCsv($crosswalkPath);
    echo 'Filas de cuenta legacy leídas: ' . count($crosswalk) . "\n";

    $conceptosUnicos = [];
    foreach ($crosswalk as $row) {
        $conceptosUnicos[$row[4]] = true; // concepto_canonico
    }

    $findConcepto = $pdo->prepare('SELECT id FROM iva_conceptos WHERE tenant_id = ? AND nombre = ?');
    $insertConcepto = $pdo->prepare('INSERT INTO iva_conceptos (tenant_id, nombre) VALUES (?, ?)');

    /** @var array<string, int> $conceptoIdPorNombre */
    $conceptoIdPorNombre = [];
    $conceptosCreados = 0;
    foreach (array_keys($conceptosUnicos) as $nombreConcepto) {
        $findConcepto->execute([$tenantId, $nombreConcepto]);
        $id = $findConcepto->fetchColumn();
        if ($id !== false) {
            $conceptoIdPorNombre[$nombreConcepto] = (int) $id;
            continue;
        }
        $insertConcepto->execute([$tenantId, $nombreConcepto]);
        $conceptoIdPorNombre[$nombreConcepto] = (int) $pdo->lastInsertId();
        $conceptosCreados++;
    }
    echo "  + conceptos canónicos nuevos: {$conceptosCreados} (total: " . count($conceptoIdPorNombre) . ")\n";

    // ── 3. Cuentas (todas) + 4. empresa_concepto_cuenta (solo preferida) ────
    $findCuenta = $pdo->prepare('SELECT id FROM cuentas WHERE empresa_id = ? AND codigo = ? AND nombre = ?');
    $insertCuenta = $pdo->prepare('INSERT INTO cuentas (empresa_id, codigo, nombre) VALUES (?, ?, ?)');
    $findEcc = $pdo->prepare('SELECT id FROM empresa_concepto_cuenta WHERE empresa_id = ? AND concepto_id = ?');
    $insertEcc = $pdo->prepare(
        'INSERT INTO empresa_concepto_cuenta (empresa_id, concepto_id, cuenta_id) VALUES (?, ?, ?)'
    );

    $cuentasCreadas = 0;
    $cuentasOmitidas = 0;
    $eccCreados = 0;
    $sinEmpresa = 0;

    foreach ($crosswalk as [$cuentaIdLegacy, $empresaIdLegacy, $codigo, $nombre, $concepto, $preferida]) {
        $empresaId = $empresaIdPorLegacy[(int) $empresaIdLegacy] ?? null;
        if ($empresaId === null) {
            $sinEmpresa++;
            continue;
        }

        $findCuenta->execute([$empresaId, $codigo, $nombre]);
        $cuentaId = $findCuenta->fetchColumn();
        if ($cuentaId === false) {
            $insertCuenta->execute([$empresaId, $codigo !== '' ? $codigo : null, $nombre]);
            $cuentaId = (int) $pdo->lastInsertId();
            $cuentasCreadas++;
        } else {
            $cuentasOmitidas++;
        }

        if ((int) $preferida !== 1) {
            continue;
        }

        $conceptoId = $conceptoIdPorNombre[$concepto];
        $findEcc->execute([$empresaId, $conceptoId]);
        if ($findEcc->fetchColumn() !== false) {
            continue;
        }
        $insertEcc->execute([$empresaId, $conceptoId, $cuentaId]);
        $eccCreados++;
    }

    echo "  + cuentas nuevas: {$cuentasCreadas} (ya existían: {$cuentasOmitidas})\n";
    echo "  + empresa_concepto_cuenta nuevos: {$eccCreados}\n";
    if ($sinEmpresa > 0) {
        echo "  ! filas sin empresa mapeada (revisar): {$sinEmpresa}\n";
    }

    $pdo->commit();
    echo "OK — commit.\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error, rollback: ' . $e->getMessage() . "\n");
    exit(1);
}
