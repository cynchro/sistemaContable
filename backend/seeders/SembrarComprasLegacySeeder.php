<?php

/**
 * Siembra las compras reales del legacy Visual IVA — Etapa 5 del satélite (escalado a
 * todos los contribuyentes, documento-1.md §9). Origen: exportación de `COMPRAS` +
 * `COMPRAS_DISCRIMINACION` de la base de producción real (636 MB, `~/Descargas/
 * VISUALIVA - copia (7).fdb`), 402.563 comprobantes / 418.035 líneas de alícuota,
 * 2015-2026, sin filtrar (decisión del usuario: migración histórica completa).
 *
 * Uso:
 *   php seeders/SembrarComprasLegacySeeder.php
 *
 * Requiere haber corrido antes SembrarCuentasLegacySeeder.php (empresas/cuentas/
 * conceptos) y SembrarPadronProveedoresLegacySeeder.php (iva_sujetos + activaciones +
 * concepto_default_id) — se reusan para resolver proveedor_id y la cuenta contable.
 *
 * Archivos de entrada (seeders/data/, gitignoreados — datos reales), pipe-delimited,
 * sin encabezado, generados directo desde Firebird (ver softContable, isql):
 *  - compras_legacy.csv: cmp_id;empresa_id_legacy;fecha;tc_id;condicion_id;prov_id;
 *    proveedor_nombre;cuit;letra;punto_venta;numero;total;neto_no_grav;exento;
 *    imp_interno;campo_aux;fecha_cai;cai;concepto;tipo_moneda_id;tipo_cambio
 *  - compra_discriminaciones_legacy.csv: cmp_id;neto_gravado;iva_alicuota;iva_importe;
 *    iva_inc_alicuota;iva_inc_importe
 *
 * Qué siembra:
 *  1. `periodos`: uno por (empresa, año-mes) que aparezca en los datos, creado on-demand
 *     (nombre "Mes AAAA", igual convención que el legacy PERIODO_NOMBRE). No se reusa el
 *     PERIODO_ID legacy (esa tabla tiene datos sucios — períodos duplicados/mal
 *     nombrados, ej. "agosto 2" — se deriva directo de la fecha del comprobante).
 *  2. `compras` (cabecera): proveedor_id resuelto por CUIT contra `iva_sujetos` (ya
 *     sembrado); catálogos (tipo_comprobante/condición IVA/provincia/moneda) resueltos
 *     1:1 por id legacy, sólo si ese id existe en la tabla destino (los ids AFIP se
 *     preservaron al sembrar los catálogos) — si no, queda NULL en vez de romper la FK.
 *     `origen_legacy_id` = CMP_ID (trazabilidad + idempotencia).
 *  3. `compra_discriminaciones`: una fila por línea de alícuota. `cf_computable` =
 *     `iva_importe` siempre (decisión A3 del contador: cómputo 100% del CF sin
 *     prorrateo — el campo legacy CF_COMPUTABLE está prácticamente sin usar en origen,
 *     26 de 418.035 filas). `cuenta_id` resuelto con la MISMA cadena de precedencia que
 *     `ImputacionContableRepository::resolverCuenta` salvo el nivel de excepción por
 *     punto de venta (`iva_sujeto_punto_venta`, config manual sin datos legacy que
 *     migrar): excepción de concepto del proveedor en esa empresa → concepto default
 *     global del proveedor → cuenta mapeada para (empresa, concepto). Es la misma cuenta
 *     para todas las líneas del comprobante (igual que hace `CompraService::preparar()`
 *     al ingestar uno nuevo).
 *
 * Deliberadamente FUERA de esta migración histórica (documentado, no un olvido):
 *  - `compra_retenciones` (RETENC_COMPRAS del legacy): retenciones/percepciones
 *    sufridas — resuelto por diseño como insumo externo (ver ecosistema/CLAUDE.md,
 *    "Retenciones/percepciones sufridas"), no forma parte del alcance del satélite.
 *  - `rubro_id`, `tipo_operacion_compra_id`, `cuenta_debe_id`/`cuenta_haber_id`
 *    (contrapartida de comprobante): dimensiones de clasificación manual/UI, sin
 *    equivalente 1:1 confiable en el legacy.
 *
 * Idempotente: `origen_legacy_id` es UNIQUE en `compras`; el set de ids ya importados se
 * carga una vez al arrancar y cada fila del CSV que ya esté se saltea (comprobante +
 * sus líneas), sin volver a tocar la base para esas filas.
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
$comprasPath = $dataDir . '/compras_legacy.csv';
$discPath = $dataDir . '/compra_discriminaciones_legacy.csv';
$empresasPath = $dataDir . '/empresas_legacy.csv';

foreach ([$comprasPath, $discPath, $empresasPath] as $p) {
    if (!is_file($p)) {
        fwrite(STDERR, "No se encontró {$p} (dato real, gitignoreado — pedirlo aparte).\n");
        exit(1);
    }
}

const MESES = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

function normalizarCuit(string $cuit): string
{
    return preg_replace('/\D+/', '', $cuit) ?? '';
}

/** @return array<int, int> mapa id -> id (set de existencia) */
function cargarIdsValidos(PDO $pdo, string $tabla): array
{
    $ids = [];
    foreach ($pdo->query("SELECT id FROM {$tabla}")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ids[(int) $id] = (int) $id;
    }
    return $ids;
}

$tenantId = (string) $pdo->query('SELECT id FROM tenants ORDER BY id LIMIT 1')->fetchColumn();
if ($tenantId === '') {
    fwrite(STDERR, "No hay ningún tenant en la base.\n");
    exit(1);
}
echo "Tenant: {$tenantId}\n";

// ── Mapas base (chicos, en memoria) ─────────────────────────────────────────────
/** @var array<int, int> $empresaIdPorLegacy */
$empresaIdPorLegacy = [];
$findEmpresaByCuit = $pdo->prepare('SELECT id FROM empresas WHERE tenant_id = ? AND cuit = ?');
$fh = fopen($empresasPath, 'r');
fgetcsv($fh, 0, ';');
while (($row = fgetcsv($fh, 0, ';')) !== false) {
    if ($row === [null]) {
        continue;
    }
    [$empresaIdLegacy, $cuitEmpresa] = $row;
    $cuitEmpresa = normalizarCuit($cuitEmpresa);
    if (strlen($cuitEmpresa) !== 11) {
        continue;
    }
    $findEmpresaByCuit->execute([$tenantId, $cuitEmpresa]);
    $id = $findEmpresaByCuit->fetchColumn();
    if ($id !== false) {
        $empresaIdPorLegacy[(int) $empresaIdLegacy] = (int) $id;
    }
}
fclose($fh);
echo 'Empresas resueltas por CUIT: ' . count($empresaIdPorLegacy) . "\n";

/** @var array<string, int> $sujetoIdPorCuit */
$sujetoIdPorCuit = [];
$stmt = $pdo->prepare('SELECT cuit, id FROM iva_sujetos WHERE tenant_id = ?');
$stmt->execute([$tenantId]);
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $cuit => $id) {
    $sujetoIdPorCuit[$cuit] = (int) $id;
}
echo 'Sujetos (padrón) cargados: ' . count($sujetoIdPorCuit) . "\n";

$tcValidos = cargarIdsValidos($pdo, 'tipos_comprobante');
$condicionValidos = cargarIdsValidos($pdo, 'condiciones_iva');
$provinciaValidos = cargarIdsValidos($pdo, 'provincias');
$monedaValidos = cargarIdsValidos($pdo, 'tipos_moneda');

// Resolución de cuenta contable por defecto: excepción de concepto por empresa →
// concepto default global del sujeto → cuenta mapeada (empresa, concepto). Misma
// cadena que ImputacionContableRepository::resolverCuenta sin el nivel de PV.
/** @var array<int, int> $conceptoDefaultPorSujeto sujeto_id -> concepto_id */
$conceptoDefaultPorSujeto = [];
foreach (
    $pdo->query('SELECT id, concepto_default_id FROM iva_sujetos WHERE concepto_default_id IS NOT NULL')
        ->fetchAll(PDO::FETCH_KEY_PAIR) as $sujetoId => $conceptoId
) {
    $conceptoDefaultPorSujeto[(int) $sujetoId] = (int) $conceptoId;
}
/** @var array<string, int> $conceptoExcepcionPorEmpresaSujeto "empresaId-sujetoId" -> concepto_id */
$conceptoExcepcionPorEmpresaSujeto = [];
foreach (
    $pdo->query(
        "SELECT empresa_id, sujeto_id, concepto_id FROM iva_sujeto_empresas
         WHERE rol = 'proveedor' AND concepto_id IS NOT NULL"
    )->fetchAll(PDO::FETCH_ASSOC) as $row
) {
    $conceptoExcepcionPorEmpresaSujeto[$row['empresa_id'] . '-' . $row['sujeto_id']] = (int) $row['concepto_id'];
}
/** @var array<string, int> $cuentaPorEmpresaConcepto "empresaId-conceptoId" -> cuenta_id */
$cuentaPorEmpresaConcepto = [];
foreach (
    $pdo->query('SELECT empresa_id, concepto_id, cuenta_id FROM empresa_concepto_cuenta')
        ->fetchAll(PDO::FETCH_ASSOC) as $row
) {
    $cuentaPorEmpresaConcepto[$row['empresa_id'] . '-' . $row['concepto_id']] = (int) $row['cuenta_id'];
}
echo 'Defaults de concepto: ' . count($conceptoDefaultPorSujeto)
    . ' | excepciones por empresa: ' . count($conceptoExcepcionPorEmpresaSujeto)
    . ' | mapeos concepto->cuenta: ' . count($cuentaPorEmpresaConcepto) . "\n";

function resolverCuentaCompra(
    int $empresaId,
    ?int $sujetoId,
    array $conceptoExcepcion,
    array $conceptoDefault,
    array $cuentaPorEmpresaConcepto
): ?int {
    if ($sujetoId === null) {
        return null;
    }
    $conceptoId = $conceptoExcepcion[$empresaId . '-' . $sujetoId] ?? $conceptoDefault[$sujetoId] ?? null;
    if ($conceptoId === null) {
        return null;
    }
    return $cuentaPorEmpresaConcepto[$empresaId . '-' . $conceptoId] ?? null;
}

// ── Períodos: cache en memoria, find-or-create por (empresa, año-mes) ───────────
/** @var array<string, int> $periodoIdPorEmpresaMes "empresaId-YYYY-MM" -> periodo_id */
$periodoIdPorEmpresaMes = [];
foreach (
    $pdo->query('SELECT id, empresa_id, fecha_ini FROM periodos WHERE fecha_ini IS NOT NULL')
        ->fetchAll(PDO::FETCH_ASSOC) as $row
) {
    $periodoIdPorEmpresaMes[$row['empresa_id'] . '-' . substr($row['fecha_ini'], 0, 7)] = (int) $row['id'];
}
$insertPeriodo = $pdo->prepare(
    'INSERT INTO periodos (empresa_id, nombre, fecha_ini, fecha_fin) VALUES (?, ?, ?, ?)'
);

function resolverPeriodo(
    PDO $pdo,
    \PDOStatement $insertPeriodo,
    array &$cache,
    int $empresaId,
    string $fecha
): int {
    $ym = substr($fecha, 0, 7);
    $key = $empresaId . '-' . $ym;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    [$anio, $mes] = array_map('intval', explode('-', $ym));
    $fechaIni = sprintf('%04d-%02d-01', $anio, $mes);
    $fechaFin = date('Y-m-t', strtotime($fechaIni));
    $nombre = MESES[$mes] . ' ' . $anio;
    $insertPeriodo->execute([$empresaId, $nombre, $fechaIni, $fechaFin]);
    $id = (int) $pdo->lastInsertId();
    $cache[$key] = $id;
    return $id;
}

// ── origen_legacy_id ya importados (idempotencia) ────────────────────────────────
$yaImportados = [];
foreach (
    $pdo->query('SELECT origen_legacy_id FROM compras WHERE origen_legacy_id IS NOT NULL')
        ->fetchAll(PDO::FETCH_COLUMN) as $id
) {
    $yaImportados[(int) $id] = true;
}
echo 'Compras ya importadas (se saltean): ' . count($yaImportados) . "\n";

// ── Pass 1: compras (cabecera) ───────────────────────────────────────────────────
$insertCompra = $pdo->prepare(
    'INSERT INTO compras (
        origen_legacy_id, periodo_id, tipo_comprobante_id, condicion_iva_id, provincia_id,
        tipo_moneda_id, proveedor_id, fecha, proveedor_nombre, cuit, letra, punto_venta,
        numero, neto_no_grav, exento, imp_interno, total, tipo_cambio, concepto, cai,
        fecha_cai, campo_auxiliar
    ) VALUES (
        :origen_legacy_id, :periodo_id, :tipo_comprobante_id, :condicion_iva_id, :provincia_id,
        :tipo_moneda_id, :proveedor_id, :fecha, :proveedor_nombre, :cuit, :letra, :punto_venta,
        :numero, :neto_no_grav, :exento, :imp_interno, :total, :tipo_cambio, :concepto, :cai,
        :fecha_cai, :campo_auxiliar
    )'
);

/** @var array<int, int> $nuevosCompraIdPorLegacy CMP_ID legacy -> compras.id nuevo (solo esta corrida) */
$nuevosCompraIdPorLegacy = [];
/** @var array<int, int|null> $cuentaPorCompraLegacy CMP_ID legacy -> cuenta_id resuelta (solo esta corrida) */
$cuentaPorCompraLegacy = [];
$leidas = 0;
$creadas = 0;
$sinEmpresa = 0;
$malformadas = 0;

$fh = fopen($comprasPath, 'r');
$pdo->beginTransaction();
while (($linea = fgets($fh)) !== false) {
    $linea = rtrim($linea, "\r\n");
    if ($linea === '') {
        continue;
    }
    $campos = array_map(fn ($v) => (trim($v) === '' ? '' : (mb_check_encoding(trim($v), 'UTF-8') ? trim($v) : mb_convert_encoding(trim($v), 'UTF-8', 'ISO-8859-1'))), explode('|', $linea));
    if (count($campos) !== 21) {
        $malformadas++;
        continue;
    }
    $leidas++;
    [
        $cmpId, $empresaIdLegacy, $fecha, $tcId, $condicionId, $provId,
        $proveedorNombre, $cuit, $letra, $pventa, $nroComp,
        $total, $netoNoGrav, $exento, $impInterno,
        $campoAux, $fechaCai, $cai, $tipoConcepto, $tipoMonedaId, $tipoCambio,
    ] = $campos;

    $cmpId = (int) $cmpId;
    if (isset($yaImportados[$cmpId])) {
        continue;
    }

    $empresaId = $empresaIdPorLegacy[(int) $empresaIdLegacy] ?? null;
    if ($empresaId === null) {
        $sinEmpresa++;
        continue;
    }

    $periodoId = resolverPeriodo($pdo, $insertPeriodo, $periodoIdPorEmpresaMes, $empresaId, $fecha);
    $cuitNorm = normalizarCuit($cuit);
    $proveedorId = $cuitNorm !== '' ? ($sujetoIdPorCuit[$cuitNorm] ?? null) : null;

    $insertCompra->execute([
        'origen_legacy_id' => $cmpId,
        'periodo_id' => $periodoId,
        'tipo_comprobante_id' => $tcValidos[(int) $tcId] ?? null,
        'condicion_iva_id' => $condicionId !== '' && isset($condicionValidos[(int) $condicionId])
            ? (int) $condicionId : null,
        'provincia_id' => $provId !== '' && isset($provinciaValidos[(int) $provId]) ? (int) $provId : null,
        'tipo_moneda_id' => $tipoMonedaId !== '' && isset($monedaValidos[(int) $tipoMonedaId])
            ? (int) $tipoMonedaId : null,
        'proveedor_id' => $proveedorId,
        'fecha' => $fecha,
        'proveedor_nombre' => $proveedorNombre !== '' ? $proveedorNombre : null,
        'cuit' => $cuitNorm !== '' ? $cuitNorm : null,
        'letra' => $letra !== '' ? $letra : null,
        'punto_venta' => $pventa !== '' ? $pventa : null,
        'numero' => $nroComp !== '' ? $nroComp : null,
        'neto_no_grav' => $netoNoGrav,
        'exento' => $exento,
        'imp_interno' => $impInterno,
        'total' => $total,
        'tipo_cambio' => $tipoCambio !== '' ? $tipoCambio : null,
        'concepto' => $tipoConcepto !== '' ? (int) $tipoConcepto : 1,
        'cai' => $cai !== '' ? $cai : null,
        'fecha_cai' => $fechaCai !== '' ? $fechaCai : null,
        'campo_auxiliar' => $campoAux !== '' ? $campoAux : null,
    ]);
    $compraId = (int) $pdo->lastInsertId();
    $nuevosCompraIdPorLegacy[$cmpId] = $compraId;
    $cuentaPorCompraLegacy[$cmpId] = resolverCuentaCompra(
        $empresaId,
        $proveedorId,
        $conceptoExcepcionPorEmpresaSujeto,
        $conceptoDefaultPorSujeto,
        $cuentaPorEmpresaConcepto
    );
    $creadas++;

    if ($creadas % 2000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  ... {$creadas} compras nuevas\n";
    }
}
fclose($fh);
$pdo->commit();

echo "Compras leídas: {$leidas} | nuevas: {$creadas} | sin empresa mapeada: {$sinEmpresa}"
    . " | filas malformadas (saltadas): {$malformadas}\n";

// ── Pass 2: compra_discriminaciones (solo para las compras nuevas de esta corrida) ──
$insertDisc = $pdo->prepare(
    'INSERT INTO compra_discriminaciones (
        compra_id, cuenta_id, neto_gravado, iva_alicuota, iva_importe,
        iva_inc_alicuota, iva_inc_importe, cf_computable
    ) VALUES (
        :compra_id, :cuenta_id, :neto_gravado, :iva_alicuota, :iva_importe,
        :iva_inc_alicuota, :iva_inc_importe, :cf_computable
    )'
);

$discLeidas = 0;
$discCreadas = 0;
$discSinCompra = 0;
$discMalformadas = 0;

$fh = fopen($discPath, 'r');
$pdo->beginTransaction();
while (($linea = fgets($fh)) !== false) {
    $linea = rtrim($linea, "\r\n");
    if ($linea === '') {
        continue;
    }
    $campos = array_map(fn ($v) => (trim($v) === '' ? '' : (mb_check_encoding(trim($v), 'UTF-8') ? trim($v) : mb_convert_encoding(trim($v), 'UTF-8', 'ISO-8859-1'))), explode('|', $linea));
    if (count($campos) !== 6) {
        $discMalformadas++;
        continue;
    }
    $discLeidas++;
    [$cmpId, $netoGravado, $ivaAlicuota, $ivaImporte, $ivaIncAlicuota, $ivaIncImporte] = $campos;
    $cmpId = (int) $cmpId;

    $compraId = $nuevosCompraIdPorLegacy[$cmpId] ?? null;
    if ($compraId === null) {
        // No es nueva esta corrida (ya importada antes, se saltea) o su cabecera
        // se descartó (empresa sin mapear / fila malformada) — no es un error.
        $discSinCompra++;
        continue;
    }

    $insertDisc->execute([
        'compra_id' => $compraId,
        'cuenta_id' => $cuentaPorCompraLegacy[$cmpId],
        'neto_gravado' => $netoGravado,
        'iva_alicuota' => $ivaAlicuota !== '' ? $ivaAlicuota : null,
        'iva_importe' => $ivaImporte,
        'iva_inc_alicuota' => $ivaIncAlicuota !== '' ? $ivaIncAlicuota : null,
        'iva_inc_importe' => $ivaIncImporte,
        'cf_computable' => $ivaImporte,
    ]);
    $discCreadas++;

    if ($discCreadas % 2000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  ... {$discCreadas} líneas de discriminación nuevas\n";
    }
}
fclose($fh);
$pdo->commit();

echo "Discriminaciones leídas: {$discLeidas} | nuevas: {$discCreadas}"
    . " | sin compra nueva (ya importada o descartada): {$discSinCompra}"
    . " | filas malformadas (saltadas): {$discMalformadas}\n";
echo "OK.\n";
