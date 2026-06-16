<?php

namespace App\Modules\Iva\Afip\Padron;

/**
 * Consulta al padrón de contribuyentes de AFIP por CUIT. Implementaciones reusan el
 * WSAA para autenticarse. Interfaz para poder sustituirla en tests (sin red/cert).
 */
interface PadronClient
{
    public function consultar(string $cuit): PersonaPadron;
}
