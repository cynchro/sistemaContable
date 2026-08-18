<?php

namespace App\Modules\Compartido\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Sige\SigeClient;

/**
 * Consulta de contribuyentes en el SIGE (sistemaCuarto) por CUIT, para autocompletar
 * el alta de empresa sin duplicar la carga. Fuente preferida de identidad/CRM del
 * contribuyente; no reemplaza al padrón de AFIP (domicilio/condición IVA/actividad
 * siguen viniendo de ahí, ver PadronService).
 */
class SigeService
{
    public function __construct(private SigeClient $client)
    {
    }

    /** @return array<string, mixed> */
    public function sugerencia(string $cuit): array
    {
        $cuit = preg_replace('/\D/', '', $cuit) ?? '';

        if (!preg_match('/^\d{11}$/', $cuit)) {
            throw new ValidationException(['cuit' => ['El CUIT debe tener 11 dígitos.']]);
        }

        $p = $this->client->buscarPorCuit($cuit);

        if ($p === null) {
            return ['encontrado' => false, 'cuit' => $cuit];
        }

        return [
            'encontrado'      => true,
            'sige_persona_id' => $p->personaId,
            'cuit'            => $p->cuit,
            'nombre'          => $p->nombre,
            'email'           => $p->email,
            'contacto'        => $p->contacto,
            'telefono'        => $p->telefono,
            'tipo_persona'    => $p->tipoPersona,
            'inscripcion'     => $p->inscripcion,
            'contabilidad'    => $p->contabilidad,
        ];
    }
}
