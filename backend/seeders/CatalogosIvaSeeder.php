<?php

/**
 * Siembra los catálogos AFIP globales del dominio IVA (módulo Compartido).
 *
 * Uso:
 *   php seeders/CatalogosIvaSeeder.php
 *
 * Origen: datos reales del sistema legacy (Visual IVA, mysql/iva_data.sql del
 * proyecto softContable). Se preservan los IDs y códigos AFIP originales — las
 * futuras ventas/compras los referencian por id. Las tildes perdidas en la
 * exportación legacy (bytes U+FFFD) se reponen con la grafía correcta; los
 * códigos AFIP y el signo de cada comprobante se mantienen intactos.
 *
 * Idempotente: si una tabla ya tiene filas, se omite.
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

// Necesario para conservar los ids legacy con valor 0 (No Disponible / Varias).
$pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");

/**
 * Inserta filas sólo si la tabla está vacía.
 *
 * @param list<string>        $columns
 * @param list<list<mixed>>   $rows
 */
$seed = static function (PDO $pdo, string $table, array $columns, array $rows): void {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    if ($count > 0) {
        echo "  - {$table}: ya tiene {$count} filas, se omite.\n";
        return;
    }

    $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $sql = sprintf('INSERT INTO %s (%s) VALUES %s', $table, implode(', ', $columns), $placeholders);
    $stmt = $pdo->prepare($sql);

    foreach ($rows as $row) {
        $stmt->execute($row);
    }

    echo "  + {$table}: " . count($rows) . " filas sembradas.\n";
};

$pdo->beginTransaction();

try {
    $seed($pdo, 'condiciones_iva', ['id', 'codigo', 'nombre', 'codigo_afip'], [
        [1, 'RI', 'Responsable Inscripto', '01'],
        [2, 'RN', 'Responsable No Inscripto', '02'],
        [3, 'MO', 'Responsable Monotributo', '06'],
        [4, 'EX', 'Exento', '04'],
        [5, 'CF', 'Consumidor Final', '05'],
        [0, '1', 'No Disponible', '00'],
        [9, 'CE', 'Cliente del exterior', '09'],
    ]);

    $seed($pdo, 'tipos_comprobante', ['id', 'codigo', 'nombre', 'cod_citi', 'acredita', 'signo'], [
        [1, 'TF', 'Ticket Factura A, B, C, M', '01', 'N', 1],
        [2, 'TI', 'Ticket (No exportable)', '01', 'N', 1],
        [3, 'NC', 'Nota de Crédito A, B, C, E, M', '03', 'S', -1],
        [4, 'ND', 'Nota de Débito A, B, C, M', '02', 'N', 1],
        [5, 'RF', 'Recibo Factura A, B, C, M', '01', 'N', 1],
        [6, 'RE', 'Recibo (No exportable)', '00', 'N', 1],
        [7, 'LI', 'Liquidación (No exportable)', '00', 'N', 1],
        [8, 'PC', 'Planilla de Caja (No exportable)', '00', 'N', 1],
        [9, 'FA', 'Factura A, B, C, E, M', '01', 'N', 1],
        [10, 'TZ', 'Ticket Z', '80', 'N', 1],
        [11, 'FT', 'Factura T', '195', 'N', 1],
        [12, 'FE', 'Factura de Crédito Electrónica MIPYMES', '201', 'N', 1],
        [13, 'DE', 'Nota de Débito Electrónica MIPYMES', '202', 'N', 1],
        [14, 'CE', 'Nota de Crédito Electrónica MIPYMES', '203', 'S', -1],
        [50, 'CR', 'Comprobante de Retención', '99', 'N', 1],
        [52, 'NC', 'Nota de Crédito T', '197', 'S', -1],
        // 53-80: verificado byte a byte contra TIPO_COMPROBANTE de la base de producción
        // real (~/Descargas/VISUALIVA - copia (7).fdb, isql) — la tabla demo de
        // softContable/mysql/iva_data.sql tenía los ids 53-61 corridos/mal mapeados
        // respecto a producción (ids 53/54 no existen en producción; LA/LB/LI/CZ/CA/CB
        // estaban en otras posiciones), lo que causaba compras/ventas mal clasificadas
        // en silencio. 24/08/2026.
        [55, 'LA', 'Liquidación de Servicios Públicos A', '017', 'N', 1],
        [56, 'LB', 'Liquidación de Servicios Públicos B', '018', 'N', 1],
        [57, 'LI', 'Liquidación (No exportable)', '00', 'N', 1],
        [58, 'OT', 'Otros comprob que no cumplen o exceptuados RG1415', '99', 'N', 1],
        [59, 'CZ', 'Nota de Crédito Tique', '110', 'S', -1],
        [60, 'CA', 'Nota de Crédito Tique A', '112', 'S', -1],
        [61, 'CB', 'Nota de Crédito Tique B', '113', 'S', -1],
        [62, 'OC', '039 OTROS COMPROBANTES A QUE CUMPLEN CON LA R G  1', '39', 'N', 1],
        [64, 'OC', '039 OTROS COMPROBANTES A QUE CUMPLEN CON LA R G  1', '39', 'N', 1],
        [65, '30', '30 COMPROBANTES DE COMPRA DE BIENES USADOS', '030', 'N', 1],
        [66, 'CT', 'Tique C', '109', 'N', 1],
        [67, 'CN', 'Tique Nota de Crédito C', '114', 'S', -1],
        [68, 'CL', 'Cuenta de Venta y Líquido Producto A', '060', 'N', 1],
        [69, '40', '40 otros comprobantes B', '040', 'N', 1],
        [70, 'LP', 'Liquidacion de compra directa Avicola', '161', 'N', 1],
        [71, 'LN', 'Liquidacion de Venta Directa Avicola-Nota de Credi', '164', 'N', 1],
        [72, 'OC', 'Agregado por importación', '40', 'N', 1],
        [73, 'TD', 'Tique Nota de Debito A', '115', 'N', 1],
        [74, 'PA', 'Liquidación de Compra A - Sector Pecuario', '183', 'N', 1],
        [75, 'DA', 'Liquidación de Compra Directa A - Sector Pecuario', '186', 'N', 1],
        [77, 'ND', 'Nota de Débito A, B, C, E, M', '02', 'N', 1],
        [78, 'DI', 'Despacho de Importación', '066', 'N', 1],
        [79, 'CC', '034 COMPROBANTES A DEL APARTADO A  INCISO F)  R.G.', '34', 'N', 1],
        [80, 'EX', 'Agregado por importación', '090', 'N', 1],
        // 81: encontrado en vivo (25/08/2026, botón "Liquidar IVA", comprobante real de compra
        // de BANCO DE LA NACION ARGENTINA) — sin esto, `mapeo.ts` del bot lo descarta en
        // silencio al "traer" (código AFIP 63 no estaba en su tabla soportada). Misma familia
        // que 'CL' (id 68, cod_citi 060, "Cuenta de Venta y Líquido Producto A") — discrimina
        // IVA igual que una Factura A, letra fija (no depende de A/B/C como FA/ND/NC).
        // Código interno 'LM' (Liquidación por Mandato) a propósito, NO 'LA': ese código ya
        // existe en `CbteTipoResolver::TABLA_FIJA['LA'] => 17` para "Liquidación de Servicios
        // Públicos A" — un tipo de comprobante totalmente distinto (AFIP 17, no 63). Reusar 'LA'
        // acá habría hecho que el Writer resolviera mal el CbteTipo al subir este comprobante.
        [81, 'LM', 'Liquidación A (por mandato)', '063', 'N', 1],
    ]);

    $seed($pdo, 'tipos_documento', ['id', 'nombre', 'cod_afip'], [
        [1, 'CUIT', 80],
        [2, 'CUIL', 86],
        [3, 'L. Enrolamiento', 89],
        [4, 'L. Cívica', 90],
        [5, 'C.I. Extranjero', 91],
        [6, 'En Tramite', 92],
        [7, 'Acta Nacimiento', 93],
        [8, 'Pasaporte', 94],
        [9, 'CI. Bs. As. RNP', 95],
        [10, 'D.N.I.', 96],
        [11, 'CI. Cap. Federal', 0],
        [12, 'Sin Identificar', 99],
        [13, 'CDI', 87],
    ]);

    $seed($pdo, 'provincias', ['id', 'nombre', 'cp', 'jurisdiccion'], [
        [1, 'Buenos Aires', 1100, 902],
        [2, 'Catamarca', null, 903],
        [3, 'Capital Federal', null, 901],
        [4, 'Corrientes', null, 905],
        [5, 'Chubut', null, 907],
        [6, 'Córdoba', null, 904],
        [7, 'Chaco', null, 906],
        [8, 'Entre Ríos', null, 908],
        [9, 'Formosa', null, 909],
        [10, 'Jujuy', null, 910],
        [11, 'La Pampa', null, 911],
        [12, 'La Rioja', null, 912],
        [13, 'Misiones', null, 914],
        [14, 'Mendoza', null, 913],
        [15, 'Neuquén', null, 915],
        [16, 'Río Negro', null, 916],
        [17, 'Salta', null, 917],
        [18, 'Santa Cruz', null, 920],
        [19, 'Santiago del Estero', null, 922],
        [20, 'Santa Fe', null, 921],
        [21, 'San Juan', null, 918],
        [22, 'San Luis', null, 919],
        [23, 'Tierra del Fuego', null, 923],
        [24, 'Tucumán', 123, 924],
    ]);

    $seed($pdo, 'tipos_retencion', ['id', 'cod_afip', 'alicuota', 'nombre', 'tipo_rg3685', 'provincia_id'], [
        [0, '', 0, 'Varias (No exportable)', 0, null],
        [1, '1', 0, 'Percepcion IVA', 1, null],
        [2, '2', 0, 'Percepciones Nacionales', 2, null],
        [3, '3', 0, 'Percepcion IIBB', 3, null],
        [4, '4', 0, 'Percepciones Municipales', 4, null],
        [5, '5', 0, 'Percepción a no categorizados', 5, null],
    ]);

    $seed($pdo, 'tipos_operacion_compra', ['id', 'codigo', 'nombre'], [
        [1, '1', 'Compras de bienes (excepto bienes de uso)'],
        [2, '2', 'Locaciones'],
        [3, '3', 'Prestaciones de servicio'],
        [4, '4', 'Inversiones en bienes de uso'],
        [5, '5', 'Compras de bienes usados a consumidores finales'],
        [6, '6', 'Tur IVA'],
        [7, '7', 'Contribuciones de la seguridad social'],
        [8, '8', 'Otros conceptos'],
    ]);

    $seed($pdo, 'tipos_operacion_venta', ['id', 'codigo', 'nombre'], [
        [1, '1', 'Ventas de cosas muebles, obras, locaciones y/o prestaciones de servicios'],
        [2, '2', 'Venta de bienes de uso'],
    ]);

    $seed($pdo, 'tipos_moneda', ['id', 'codigo_afip', 'nombre'], [
        [1, '000', 'OTRAS MONEDAS'],
        [2, 'PES', 'PESOS'],
        [3, 'DOL', 'Dólar ESTADOUNIDENSE'],
        [4, '002', 'Dólar EEUU LIBRE'],
        [5, '003', 'FRANCOS FRANCESES'],
        [6, '004', 'LIRAS ITALIANAS'],
        [7, '005', 'PESETAS'],
        [8, '006', 'MARCOS ALEMANES'],
        [9, '007', 'FLORINES HOLANDESES'],
        [10, '008', 'FRANCOS BELGAS'],
        [11, '009', 'FRANCOS SUIZOS'],
        [12, '010', 'PESOS MEJICANOS'],
        [13, '011', 'PESOS URUGUAYOS'],
        [14, '012', 'REAL'],
        [15, '013', 'ESCUDOS PORTUGUESES'],
        [16, '014', 'CORONAS DANESAS'],
        [17, '015', 'CORONAS NORUEGAS'],
        [18, '016', 'CORONAS SUECAS'],
        [19, '017', 'CHELINES AUSTRIACOS'],
        [20, '018', 'Dólar CANADIENSE'],
        [21, '019', 'YENS'],
        [22, '021', 'LIBRA ESTERLINA'],
        [23, '022', 'MARCOS FINLANDESES'],
        [24, '023', 'BOLIVAR (VENEZOLANO)'],
        [25, '024', 'CORONA CHECA'],
        [26, '025', 'DINAR (YUGOSLAVO)'],
        [27, '026', 'Dólar AUSTRALIANO'],
        [28, '027', 'DRACMA (GRIEGO)'],
        [29, '028', 'FLORIN (ANTILLAS HOLANDESAS)'],
        [30, '029', 'GUARANI'],
        [31, '030', 'SHEKEL (ISRAEL)'],
        [32, '031', 'PESO BOLIVIANO'],
        [33, '032', 'PESO COLOMBIANO'],
        [34, '033', 'PESO CHILENO'],
        [35, '034', 'RAND (SUDAFRICANO)'],
        [36, '035', 'NUEVO SOL PERUANO'],
        [37, '036', 'SUCRE (ECUATORIANO)'],
        [38, '040', 'LEI RUMANOS'],
        [39, '041', 'DERECHOS ESPECIALES DE GIRO'],
        [40, '042', 'PESOS DOMINICANOS'],
        [41, '043', 'BALBOAS PANAMEÑAS'],
        [42, '044', 'CORDOBAS NICARAGÜENSES'],
        [43, '045', 'DIRHAM MARROQUÍES'],
        [44, '046', 'LIBRAS EGIPCIAS'],
        [45, '047', 'RIYALS SAUDITAS'],
        [46, '048', 'FRANCOS BELGAS FINANCIERAS'],
        [47, '049', 'GRAMOS DE ORO FINO'],
        [48, '050', 'LIBRAS IRLANDESAS'],
        [49, '051', 'Dólar DE HONG KONG'],
        [50, '052', 'Dólar DE SINGAPUR'],
        [51, '053', 'Dólar DE JAMAICA'],
        [52, '054', 'Dólar DE TAIWAN'],
        [53, '055', 'QUETZAL (GUATEMALTECOS)'],
        [54, '056', 'FORINT (HUNGRIA)'],
        [55, '057', 'BAHT (TAILANDIA)'],
        [56, '058', 'ECU'],
        [57, '059', 'DINAR KUWAITI'],
        [58, '060', 'EURO'],
        [59, '061', 'ZLOTYS POLACOS'],
        [60, '062', 'RUPIAS HINDÚES'],
        [61, '063', 'LEMPIRAS HONDUREÑAS'],
        [62, '064', 'YUAN (Rep. Pop. China)'],
    ]);

    $pdo->commit();
    echo "Catálogos IVA sembrados correctamente.\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error sembrando catálogos: ' . $e->getMessage() . "\n");
    exit(1);
}
