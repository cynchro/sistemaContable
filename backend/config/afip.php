<?php

/**
 * Configuración de los web services de AFIP/ARCA.
 *
 * `env` selecciona el ambiente: 'homologacion' (testing) o 'produccion'.
 * El certificado X.509 y su clave privada se cargan desde archivos (rutas en .env);
 * la clave privada puede ir protegida con passphrase (cifrable con App\Support\Crypto
 * a futuro, cuando se guarden credenciales por CUIT en la base).
 */

// Normaliza un PEM inline (AFIP_CERT_PEM / AFIP_KEY_PEM): acepta el PEM crudo o su base64
// (más cómodo en variables de entorno de un PaaS, que suelen ser de una línea).
$pem = static function (?string $v): ?string {
    if ($v === null || $v === '') {
        return null;
    }
    if (str_contains($v, '-----BEGIN')) {
        return $v; // ya es PEM crudo
    }
    $decoded = base64_decode($v, true);

    return ($decoded !== false && str_contains($decoded, '-----BEGIN')) ? $decoded : $v;
};

return [
    'env'            => $_ENV['AFIP_ENV'] ?? 'homologacion',
    'cuit'           => $_ENV['AFIP_CUIT'] ?? null,
    // El certificado y la clave se pueden dar como archivo (*_PATH, dev) o inline (*_PEM,
    // recomendado en producción/PaaS: sobrevive a redeploys sin filesystem persistente).
    'cert_path'      => $_ENV['AFIP_CERT_PATH'] ?? null,
    'key_path'       => $_ENV['AFIP_KEY_PATH'] ?? null,
    'cert_pem'       => $pem($_ENV['AFIP_CERT_PEM'] ?? null),
    'key_pem'        => $pem($_ENV['AFIP_KEY_PEM'] ?? null),
    'key_passphrase' => $_ENV['AFIP_KEY_PASSPHRASE'] ?? '',

    // El PADRÓN puede usar un certificado y ambiente DISTINTOS a los de WSFE: p. ej. padrón
    // en producción (datos reales) y WSFE en homologación (no emitir facturas reales mientras
    // se prueba). Si no se definen los AFIP_PADRON_*, caen al certificado/ambiente global.
    'padron_env'            => $_ENV['AFIP_PADRON_ENV'] ?? ($_ENV['AFIP_ENV'] ?? 'homologacion'),
    'padron_cert_path'      => $_ENV['AFIP_PADRON_CERT_PATH'] ?? ($_ENV['AFIP_CERT_PATH'] ?? null),
    'padron_key_path'       => $_ENV['AFIP_PADRON_KEY_PATH'] ?? ($_ENV['AFIP_KEY_PATH'] ?? null),
    'padron_cert_pem'       => $pem($_ENV['AFIP_PADRON_CERT_PEM'] ?? null) ?? $pem($_ENV['AFIP_CERT_PEM'] ?? null),
    'padron_key_pem'        => $pem($_ENV['AFIP_PADRON_KEY_PEM'] ?? null) ?? $pem($_ENV['AFIP_KEY_PEM'] ?? null),
    'padron_key_passphrase' => $_ENV['AFIP_PADRON_KEY_PASSPHRASE'] ?? ($_ENV['AFIP_KEY_PASSPHRASE'] ?? ''),

    // Endpoints WSAA (LoginCms) por ambiente.
    'wsaa' => [
        'homologacion' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',
        'produccion'   => 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl',
    ],

    // Alcance del padrón a usar. Define WSDL + nombre del WS (ws_sr_padron_<alcance>):
    //   a13 = constancia de inscripción (default; NO informa impuestos/condición IVA).
    //   a4/a5 = padrón completo (datosRegimenGeneral: impuestos + actividades) → sí trae la
    //           condición IVA y la actividad secundaria. a10 = impuestos por período.
    // Usar el que ARCA tenga autorizado en el certificado (p. ej. AFIP_PADRON_ALCANCE=a4).
    'padron_alcance' => $_ENV['AFIP_PADRON_ALCANCE'] ?? 'a13',

    // Endpoints del padrón por alcance (consulta de contribuyentes por CUIT).
    'padron' => [
        'a4' => [
            'homologacion' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA4?wsdl',
            'produccion'   => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA4?wsdl',
        ],
        'a5' => [
            'homologacion' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5?wsdl',
            'produccion'   => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5?wsdl',
        ],
        'a10' => [
            'homologacion' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA10?wsdl',
            'produccion'   => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA10?wsdl',
        ],
        'a13' => [
            'homologacion' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA13?wsdl',
            'produccion'   => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA13?wsdl',
        ],
    ],

    // Endpoints WSFEv1 (factura electrónica) por ambiente.
    'wsfe' => [
        'homologacion' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL',
        'produccion'   => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL',
    ],

    // Margen (segundos) antes de la expiración del TA para considerarlo vencido.
    'ta_margin' => (int) ($_ENV['AFIP_TA_MARGIN'] ?? 600),
];
