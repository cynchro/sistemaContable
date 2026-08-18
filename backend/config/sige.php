<?php

/**
 * Configuración del cliente hacia el SIGE (sistemaCuarto) — sincronización de
 * identidad/CRM del contribuyente para autocompletar el alta de empresa.
 */

return [
    'base_url' => $_ENV['SIGE_BASE_URL'] ?? '',
    'api_key'  => $_ENV['SIGE_API_KEY'] ?? '',
    'timeout'  => (int) ($_ENV['SIGE_TIMEOUT'] ?? 5),
];
