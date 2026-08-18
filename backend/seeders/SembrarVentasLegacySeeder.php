<?php

/**
 * Siembra las ventas reales del legacy Visual IVA — Etapa 5 del satélite (escalado a
 * todos los contribuyentes, documento-1.md §9). Origen: exportación de `VENTAS` +
 * `VENTA_DISCRIMINACION` de la base de producción real (636 MB, `~/Descargas/
 * VISUALIVA - copia (7).fdb`), 1.146.963 comprobantes / 1.189.253 líneas de alícuota,
 * 2015-2026, sin filtrar (decisión del usuario: migración histórica completa).
 *
 * Uso:
 *   php seeders/SembrarVentasLegacySeeder.php
 *
 * Requiere haber corrido antes SembrarComprasLegacySeeder.php (mismo esquema de
 * períodos — se reusa/completa el mismo caché por empresa+mes; no hace falta que las
 * compras existan para que esto funcione solo, pero conviene el mismo orden que Etapa 5
 * documentó) y SembrarPadronProveedoresLegacySeeder.php (iva_sujetos, para resolver
 * cliente_id — el padrón único no distingue cliente/proveedor, la activación sí, pero acá
 * alcanza con el CUIT: casi la mitad de las ventas es a Consumidor Final sin CUIT).
 *
 * Archivos de entrada (seeders/data/, gitignoreados — datos reales), pipe-delimited,
 * sin encabezado, generados directo desde Firebird (ver softContable, isql):
 *  - ventas_legacy.csv: vta_id;empresa_id_legacy;fecha;tc_id;condicion_id;prov_id;td_id;
 *    cliente_nombre;cuit;letra;punto_venta;numero;numero_fin;total;neto_no_grav;exento;
 *    imp_interno;campo_aux;fecha_cai;cai;concepto;tipo_moneda_id;tipo_cambio
 *  - venta_discriminaciones_legacy.csv: vta_id;neto_gravado;iva_alicuota;iva_importe;
 *    iva_inc_alicuota;iva_inc_importe;reintegro_t;concepto
 *
 * Qué siembra:
 *  1. `periodos`: igual criterio que compras (find-or-create por empresa+año-mes,
 *     derivado de la fecha real del comprobante, no del PERIODO_ID legacy).
 *  2. `ventas` (cabecera): cliente_id resuelto por CUIT contra `iva_sujetos` (null si no
 *     hay CUIT — 493.071 de 1.146.963 son a Consumidor Final sin CUIT, se guardan igual
 *     con `cliente_nombre`/`cuit` de texto libre, como haría el importador CSV normal).
 *     Catálogos (tipo_comprobante/condición IVA/provincia/tipo_documento/moneda)
 *     resueltos 1:1 por id legacy, sólo si existen en la tabla destino. `origen_legacy_id`
 *     = VTA_ID (trazabilidad + idempotencia).
 *  3. `venta_discriminaciones`: una fila por línea de alícuota, con `reintegro_t` y
 *     `concepto` copiados directo (mismos campos que el legacy, sin transformar — ya
 *     soportados por el motor: reintegro_t para Factura T, concepto para DJ IVA Simple).
 *
 * Deliberadamente FUERA de esta migración histórica (documentado, no un olvido):
 *  - `venta_retenciones` (RETENCIONES del legacy): igual criterio que compras.
 *  - `cuenta_id` por línea (clasificación por punto de venta, Pantalla D): depende de
 *    reglas `iva_venta_punto_venta`/`_tipo` configuradas a mano por empresa — no hay
 *    equivalente en los datos legacy para backfillear; queda NULL, se completa
 *    configurando las reglas por PV (ya construidas) empresa por empresa.
 *  - `rubro_id`, `tipo_operacion_venta_id`, `id_actividad`,
 *    `cuenta_debe_id`/`cuenta_haber_id`: mismo criterio que compras.
 *  - `anulado`/`fecha_anulacion`: el legacy no tiene un flag equivalente exportado acá
 *    (no se investigó una fuente confiable) — todo importa como no anulado.
 *
 * Idempotente: `origen_legacy_id` es UNIQUE en `ventas`; mismo mecanismo de skip-set que
 * el seeder de compras.
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
$ventasPath = $dataDir . '/ventas_legacy.csv';
$discPath = $dataDir . '/venta_discriminaciones_legacy.csv';
$empresasPath = $dataDir . '/empresas_legacy.csv';

foreach ([$ventasPath, $discPath, $empresasPath] as $p) {
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
$tipoDocValidos = cargarIdsValidos($pdo, 'tipos_documento');

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
    $pdo->query('SELECT origen_legacy_id FROM ventas WHERE origen_legacy_id IS NOT NULL')
        ->fetchAll(PDO::FETCH_COLUMN) as $id
) {
    $yaImportados[(int) $id] = true;
}
echo 'Ventas ya importadas (se saltean): ' . count($yaImportados) . "\n";

// ── Pass 1: ventas (cabecera) ─────────────────────────────────────────────────────
$insertVenta = $pdo->prepare(
    'INSERT INTO ventas (
        origen_legacy_id, periodo_id, tipo_comprobante_id, tipo_documento_id, condicion_iva_id,
        provincia_id, tipo_moneda_id, cliente_id, fecha, cliente_nombre, cuit, letra,
        punto_venta, numero, numero_fin, neto_no_grav, exento, imp_interno, total,
        tipo_cambio, concepto, cai, fecha_cai, campo_auxiliar
    ) VALUES (
        :origen_legacy_id, :periodo_id, :tipo_comprobante_id, :tipo_documento_id, :condicion_iva_id,
        :provincia_id, :tipo_moneda_id, :cliente_id, :fecha, :cliente_nombre, :cuit, :letra,
        :punto_venta, :numero, :numero_fin, :neto_no_grav, :exento, :imp_interno, :total,
        :tipo_cambio, :concepto, :cai, :fecha_cai, :campo_auxiliar
    )'
);

/** @var array<int, int> $nuevosVentaIdPorLegacy VTA_ID legacy -> ventas.id nuevo (solo esta corrida) */
$nuevosVentaIdPorLegacy = [];
$leidas = 0;
$creadas = 0;
$sinEmpresa = 0;
$malformadas = 0;

$fh = fopen($ventasPath, 'r');
$pdo->beginTransaction();
while (($linea = fgets($fh)) !== false) {
    $linea = rtrim($linea, "\r\n");
    if ($linea === '') {
        continue;
    }
    $campos = array_map(fn ($v) => (trim($v) === '' ? '' : (mb_check_encoding(trim($v), 'UTF-8') ? trim($v) : mb_convert_encoding(trim($v), 'UTF-8', 'ISO-8859-1'))), explode('|', $linea));
    if (count($campos) !== 23) {
        $malformadas++;
        continue;
    }
    $leidas++;
    [
        $vtaId, $empresaIdLegacy, $fecha, $tcId, $condicionId, $provId, $tdId,
        $clienteNombre, $cuit, $letra, $pventa, $nroComp, $nroFinComp,
        $total, $netoNoGrav, $exento, $impInterno,
        $campoAux, $fechaCai, $cai, $tipoConcepto, $tipoMonedaId, $tipoCambio,
    ] = $campos;

    $vtaId = (int) $vtaId;
    if (isset($yaImportados[$vtaId])) {
        continue;
    }

    $empresaId = $empresaIdPorLegacy[(int) $empresaIdLegacy] ?? null;
    if ($empresaId === null) {
        $sinEmpresa++;
        continue;
    }

    $periodoId = resolverPeriodo($pdo, $insertPeriodo, $periodoIdPorEmpresaMes, $empresaId, $fecha);
    $cuitNorm = normalizarCuit($cuit);
    $clienteId = $cuitNorm !== '' ? ($sujetoIdPorCuit[$cuitNorm] ?? null) : null;

    $insertVenta->execute([
        'origen_legacy_id' => $vtaId,
        'periodo_id' => $periodoId,
        'tipo_comprobante_id' => $tcValidos[(int) $tcId] ?? null,
        'tipo_documento_id' => $tdId !== '' && isset($tipoDocValidos[(int) $tdId]) ? (int) $tdId : null,
        'condicion_iva_id' => $condicionId !== '' && isset($condicionValidos[(int) $condicionId])
            ? (int) $condicionId : null,
        'provincia_id' => $provId !== '' && isset($provinciaValidos[(int) $provId]) ? (int) $provId : null,
        'tipo_moneda_id' => $tipoMonedaId !== '' && isset($monedaValidos[(int) $tipoMonedaId])
            ? (int) $tipoMonedaId : null,
        'cliente_id' => $clienteId,
        'fecha' => $fecha,
        'cliente_nombre' => $clienteNombre !== '' ? $clienteNombre : null,
        'cuit' => $cuitNorm !== '' ? $cuitNorm : null,
        'letra' => $letra !== '' ? $letra : null,
        'punto_venta' => $pventa !== '' ? $pventa : null,
        'numero' => $nroComp !== '' ? $nroComp : null,
        'numero_fin' => $nroFinComp !== '' ? $nroFinComp : null,
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
    $nuevosVentaIdPorLegacy[$vtaId] = (int) $pdo->lastInsertId();
    $creadas++;

    if ($creadas % 2000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  ... {$creadas} ventas nuevas\n";
    }
}
fclose($fh);
$pdo->commit();

echo "Ventas leídas: {$leidas} | nuevas: {$creadas} | sin empresa mapeada: {$sinEmpresa}"
    . " | filas malformadas (saltadas): {$malformadas}\n";

// ── Pass 2: venta_discriminaciones (solo para las ventas nuevas de esta corrida) ────
$insertDisc = $pdo->prepare(
    'INSERT INTO venta_discriminaciones (
        venta_id, neto_gravado, iva_alicuota, iva_importe,
        iva_inc_alicuota, iva_inc_importe, reintegro_t, concepto
    ) VALUES (
        :venta_id, :neto_gravado, :iva_alicuota, :iva_importe,
        :iva_inc_alicuota, :iva_inc_importe, :reintegro_t, :concepto
    )'
);

$discLeidas = 0;
$discCreadas = 0;
$discSinVenta = 0;
$discMalformadas = 0;

$fh = fopen($discPath, 'r');
$pdo->beginTransaction();
while (($linea = fgets($fh)) !== false) {
    $linea = rtrim($linea, "\r\n");
    if ($linea === '') {
        continue;
    }
    $campos = array_map(fn ($v) => (trim($v) === '' ? '' : (mb_check_encoding(trim($v), 'UTF-8') ? trim($v) : mb_convert_encoding(trim($v), 'UTF-8', 'ISO-8859-1'))), explode('|', $linea));
    if (count($campos) !== 8) {
        $discMalformadas++;
        continue;
    }
    $discLeidas++;
    [
        $vtaId, $netoGravado, $ivaAlicuota, $ivaImporte,
        $ivaIncAlicuota, $ivaIncImporte, $reintegroT, $concepto,
    ] = $campos;
    $vtaId = (int) $vtaId;

    $ventaId = $nuevosVentaIdPorLegacy[$vtaId] ?? null;
    if ($ventaId === null) {
        // No es nueva esta corrida (ya importada antes, se saltea) o su cabecera
        // se descartó (empresa sin mapear / fila malformada) — no es un error.
        $discSinVenta++;
        continue;
    }

    $insertDisc->execute([
        'venta_id' => $ventaId,
        'neto_gravado' => $netoGravado,
        'iva_alicuota' => $ivaAlicuota !== '' ? $ivaAlicuota : null,
        'iva_importe' => $ivaImporte,
        'iva_inc_alicuota' => $ivaIncAlicuota !== '' ? $ivaIncAlicuota : null,
        'iva_inc_importe' => $ivaIncImporte,
        'reintegro_t' => $reintegroT !== '' ? $reintegroT : null,
        'concepto' => $concepto !== '' ? (int) $concepto : null,
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
    . " | sin venta nueva (ya importada o descartada): {$discSinVenta}"
    . " | filas malformadas (saltadas): {$discMalformadas}\n";
echo "OK.\n";
