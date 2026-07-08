<?php

/**
 * Configuración de los web services de AFIP/ARCA.
 *
 * `env` selecciona el ambiente: 'homologacion' (testing) o 'produccion'.
 * El certificado X.509 y su clave privada se cargan desde archivos (rutas en .env);
 * la clave privada puede ir protegida con passphrase (cifrable con App\Support\Crypto
 * a futuro, cuando se guarden credenciales por CUIT en la base).
 */

return [
    'env'            => $_ENV['AFIP_ENV'] ?? 'homologacion',
    'cuit'           => $_ENV['AFIP_CUIT'] ?? null,
    'cert_path'      => $_ENV['AFIP_CERT_PATH'] ?? null,
    'key_path'       => $_ENV['AFIP_KEY_PATH'] ?? null,
    'key_passphrase' => $_ENV['AFIP_KEY_PASSPHRASE'] ?? '',

    // Endpoints WSAA (LoginCms) por ambiente.
    'wsaa' => [
        'homologacion' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',
        'produccion'   => 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl',
    ],

    // Alcance del padrón a usar ('a13' por defecto: constancia de inscripción, es el que
    // ARCA habilita hoy; 'a5' sigue soportado). Define WSDL + nombre del WS (ws_sr_padron_<alcance>).
    'padron_alcance' => $_ENV['AFIP_PADRON_ALCANCE'] ?? 'a13',

    // Endpoints del padrón por alcance (consulta de contribuyentes por CUIT).
    'padron' => [
        'a5' => [
            'homologacion' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5?wsdl',
            'produccion'   => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5?wsdl',
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
