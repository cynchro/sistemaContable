<?php

/**
 * Siembra el Padrón Único de Proveedores real — segunda mitad de la Etapa 1 del
 * satélite (documento-1.md §9), complementa SembrarCuentasLegacySeeder.php (que
 * sembró empresas/cuentas/conceptos). Origen: `documentacion/Padron_Unico_
 * Proveedores.xlsx` (6.481 proveedores reales, ya deduplicados por CUIT — el
 * trabajo de depuración de `documentacion/Informe_Definitivo_Padron_Proveedores.pdf`,
 * 21/07/2026) y `documentacion/Relacion_Contribuyente_Proveedor.xlsx` (376.819 filas:
 * qué proveedor usa cada empresa, y con qué cuenta legacy — 9.914 de esas filas
 * tienen clasificación real, el resto son activaciones sin clasificar todavía).
 *
 * Uso:
 *   php seeders/SembrarPadronProveedoresLegacySeeder.php
 *
 * Requiere haber corrido antes SembrarCuentasLegacySeeder.php (empresas + los 117
 * conceptos canónicos ya deben existir).
 *
 * Archivos de entrada (seeders/data/, todos gitignoreados — CUIT real):
 *  - padron_unico_proveedores.csv: identidad única por CUIT (nombre, domicilio,
 *    localidad, cp, condición IVA).
 *  - sujeto_concepto_default.csv: cuit;concepto_default — el concepto más
 *    frecuente entre las clasificaciones reales de ese proveedor (4.241 de 6.481
 *    tienen al menos una clasificación).
 *  - import_padron_relaciones.csv: empresa_id_legacy;cuit;concepto_excepcion —
 *    una fila por cada (empresa, proveedor) real; `concepto_excepcion` solo viene
 *    cargado cuando ESA empresa clasifica a ese proveedor distinto de su default
 *    global (695 casos reales — el caso "MUCHAY SRL" del documento original es
 *    de esta naturaleza, aunque por punto de venta, no por empresa).
 *
 * Qué siembra:
 *  1. `iva_sujetos`: upsert por (tenant_id, cuit) — nombre/domicilio/condición IVA.
 *  2. `iva_sujetos.concepto_default_id`: el default de cada proveedor.
 *  3. `iva_sujeto_empresas`: activa cada (empresa, proveedor) real con rol
 *     'proveedor', y `concepto_id` SOLO en los 695 casos de excepción real —
 *     el resto resuelve por el default del punto 2 cuando haga falta imputar.
 *
 * Idempotente (upsert por CUIT / `INSERT ... ON DUPLICATE KEY UPDATE`).
 * Inserta en lotes (no una fila a la vez) por el volumen: 376.795 activaciones.
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
$padronPath = $dataDir . '/padron_unico_proveedores.csv';
$defaultsPath = $dataDir . '/sujeto_concepto_default.csv';
$relacionesPath = $dataDir . '/import_padron_relaciones.csv';
$empresasPath = $dataDir . '/empresas_legacy.csv';

foreach ([$padronPath, $defaultsPath, $relacionesPath, $empresasPath] as $p) {
    if (!is_file($p)) {
        fwrite(STDERR, "No se encontró {$p} (dato real, gitignoreado — pedirlo aparte).\n");
        exit(1);
    }
}

/** @return \Generator<list<string>> */
function leerCsvLazy(string $path): \Generator
{
    $fh = fopen($path, 'r');
    if ($fh === false) {
        throw new RuntimeException("No se pudo abrir {$path}");
    }
    fgetcsv($fh, 0, ';'); // header
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if ($row === [null]) {
            continue;
        }
        /** @var list<string> $row */
        yield $row;
    }
    fclose($fh);
}

function normalizarCuit(string $cuit): string
{
    return preg_replace('/\D+/', '', $cuit) ?? '';
}

$tenantId = (string) $pdo->query('SELECT id FROM tenants ORDER BY id LIMIT 1')->fetchColumn();
if ($tenantId === '') {
    fwrite(STDERR, "No hay ningún tenant en la base.\n");
    exit(1);
}
echo "Tenant: {$tenantId}\n";

// ── Mapas base cargados en memoria (chicos: 6.481 sujetos, 117 conceptos, 345 empresas) ──
// Clave normalizada (mb_strtoupper): la unicidad de iva_conceptos.nombre en MySQL es
// case-insensitive (colación utf8mb4_unicode_ci) — hay conceptos preexistentes de pruebas
// manuales anteriores (p. ej. "Combustibles y Lubricantes") con casing distinto al de
// nuestro CSV ("COMBUSTIBLES Y LUBRICANTES"); sin normalizar acá, el lookup en PHP (que sí
// es case-sensitive) los trata como si no existieran y se pierde el default.
$conceptoIdPorNombre = [];
$stmt = $pdo->prepare('SELECT id, nombre FROM iva_conceptos WHERE tenant_id = ?');
$stmt->execute([$tenantId]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $conceptoIdPorNombre[mb_strtoupper($row['nombre'])] = (int) $row['id'];
}
echo 'Conceptos ya cargados: ' . count($conceptoIdPorNombre) . " (deben ser 117, de SembrarCuentasLegacySeeder)\n";
if (count($conceptoIdPorNombre) === 0) {
    fwrite(STDERR, "Corré primero SembrarCuentasLegacySeeder.php.\n");
    exit(1);
}

/** @var array<string, int> $empresaIdPorLegacy */
$empresaIdPorLegacy = [];
$findEmpresaByCuit = $pdo->prepare('SELECT id FROM empresas WHERE tenant_id = ? AND cuit = ?');
foreach (leerCsvLazy($empresasPath) as [$empresaIdLegacy, $cuitEmpresa]) {
    $cuitEmpresa = normalizarCuit($cuitEmpresa);
    if (strlen($cuitEmpresa) !== 11) {
        continue;
    }
    $findEmpresaByCuit->execute([$tenantId, $cuitEmpresa]);
    $id = $findEmpresaByCuit->fetchColumn();
    if ($id !== false) {
        $empresaIdPorLegacy[$empresaIdLegacy] = (int) $id;
    }
}
echo 'Empresas resueltas por CUIT: ' . count($empresaIdPorLegacy) . "\n";

$pdo->beginTransaction();

try {
    // ── 1. iva_sujetos: upsert por CUIT ─────────────────────────────────────
    $findSujeto = $pdo->prepare('SELECT id FROM iva_sujetos WHERE tenant_id = ? AND cuit = ?');
    $insertSujeto = $pdo->prepare(
        'INSERT INTO iva_sujetos (tenant_id, cuit, nombre, domicilio, localidad, cp, condicion_iva_id)
         VALUES (:tenant_id, :cuit, :nombre, :domicilio, :localidad, :cp, :condicion_iva_id)'
    );

    /** @var array<string, int> $sujetoIdPorCuit */
    $sujetoIdPorCuit = [];
    $sujetosCreados = 0;
    $sujetosReusados = 0;

    foreach (leerCsvLazy($padronPath) as $row) {
        [$cuit, $nombre, , , $domicilio, $localidad, $cp, $condicionId] = $row;
        $cuit = normalizarCuit($cuit);
        if (strlen($cuit) !== 11) {
            continue;
        }

        $findSujeto->execute([$tenantId, $cuit]);
        $existingId = $findSujeto->fetchColumn();
        if ($existingId !== false) {
            $sujetoIdPorCuit[$cuit] = (int) $existingId;
            $sujetosReusados++;
            continue;
        }

        $insertSujeto->execute([
            'tenant_id'        => $tenantId,
            'cuit'             => $cuit,
            'nombre'           => trim($nombre) !== '' ? trim($nombre) : "Proveedor {$cuit}",
            'domicilio'        => $domicilio !== '' ? $domicilio : null,
            'localidad'        => $localidad !== '' ? $localidad : null,
            'cp'               => $cp !== '' ? $cp : null,
            'condicion_iva_id' => $condicionId !== '' ? (int) $condicionId : null,
        ]);
        $sujetoIdPorCuit[$cuit] = (int) $pdo->lastInsertId();
        $sujetosCreados++;
    }
    echo "  + iva_sujetos nuevos: {$sujetosCreados} (ya existían: {$sujetosReusados})\n";

    // ── 2. concepto_default_id por proveedor ────────────────────────────────
    $updateDefault = $pdo->prepare('UPDATE iva_sujetos SET concepto_default_id = ? WHERE id = ?');
    $defaultsAplicados = 0;
    foreach (leerCsvLazy($defaultsPath) as [$cuit, $conceptoNombre]) {
        $cuit = normalizarCuit($cuit);
        $sujetoId = $sujetoIdPorCuit[$cuit] ?? null;
        $conceptoId = $conceptoIdPorNombre[mb_strtoupper($conceptoNombre)] ?? null;
        if ($sujetoId === null || $conceptoId === null) {
            continue;
        }
        $updateDefault->execute([$conceptoId, $sujetoId]);
        $defaultsAplicados++;
    }
    echo "  + concepto_default_id aplicado a {$defaultsAplicados} proveedores\n";

    // ── 3. iva_sujeto_empresas: activaciones + excepciones, por lotes ───────
    $BATCH = 1000;
    $buffer = [];
    $sinEmpresa = 0;
    $sinSujeto = 0;
    $totalActivadas = 0;

    $flush = function () use ($pdo, &$buffer, &$totalActivadas): void {
        if ($buffer === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($buffer), '(?, ?, ?, ?)'));
        $sql = "INSERT INTO iva_sujeto_empresas (empresa_id, sujeto_id, rol, concepto_id)
                VALUES {$placeholders}
                ON DUPLICATE KEY UPDATE concepto_id = VALUES(concepto_id)";
        $stmt = $pdo->prepare($sql);
        $params = [];
        foreach ($buffer as $row) {
            array_push($params, ...$row);
        }
        $stmt->execute($params);
        $totalActivadas += count($buffer);
        $buffer = [];
    };

    foreach (leerCsvLazy($relacionesPath) as [$empresaIdLegacy, $cuit, $conceptoExcepcion]) {
        $empresaId = $empresaIdPorLegacy[$empresaIdLegacy] ?? null;
        if ($empresaId === null) {
            $sinEmpresa++;
            continue;
        }
        $cuit = normalizarCuit($cuit);
        $sujetoId = $sujetoIdPorCuit[$cuit] ?? null;
        if ($sujetoId === null) {
            $sinSujeto++;
            continue;
        }

        $conceptoId = $conceptoExcepcion !== ''
            ? ($conceptoIdPorNombre[mb_strtoupper($conceptoExcepcion)] ?? null)
            : null;

        $buffer[] = [$empresaId, $sujetoId, 'proveedor', $conceptoId];
        if (count($buffer) >= $BATCH) {
            $flush();
        }
    }
    $flush();

    echo "  + iva_sujeto_empresas activadas/actualizadas: {$totalActivadas}\n";
    if ($sinEmpresa > 0) {
        echo "  ! filas sin empresa mapeada (empresas legacy fuera del export actual): {$sinEmpresa}\n";
    }
    if ($sinSujeto > 0) {
        echo "  ! filas sin sujeto mapeado (revisar): {$sinSujeto}\n";
    }

    $pdo->commit();
    echo "OK — commit.\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error, rollback: ' . $e->getMessage() . "\n");
    exit(1);
}
