<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Modules\Iva\Afip\Padron\PadronClient;
use App\Modules\Iva\Afip\Padron\PersonaPadron;

/**
 * Consulta de padrón AFIP por CUIT, para autocompletar datos de clientes/proveedores.
 * Valida el formato del CUIT antes de pegarle a AFIP.
 */
class PadronService
{
    public function __construct(private PadronClient $padron)
    {
    }

    public function consultar(string $cuit): PersonaPadron
    {
        $cuit = preg_replace('/\D/', '', $cuit) ?? '';

        if (!preg_match('/^\d{11}$/', $cuit)) {
            throw new ValidationException(['cuit' => ['El CUIT debe tener 11 dígitos.']]);
        }

        return $this->padron->consultar($cuit);
    }

    /**
     * Mapea los datos de padrón a los campos del alta de cliente/proveedor (autocompletar).
     * Solo mapea lo que es directo y seguro (nombre/cuit/domicilio/localidad); la condición
     * de IVA y la provincia las resuelve el front contra los catálogos, usando `padron`.
     *
     * @return array<string, mixed>
     */
    public function sugerencia(string $cuit): array
    {
        $p = $this->consultar($cuit);

        return [
            'nombre'    => $p->denominacion,
            'cuit'      => $p->cuit,
            'domicilio' => $p->domicilio['direccion'] ?? null,
            'localidad' => $p->domicilio['localidad'] ?? null,
            // Datos crudos del padrón para que el front complete los desplegables
            // (condición de IVA, provincia) y muestre el resto.
            'padron'    => [
                'tipo_persona' => $p->tipoPersona,
                'estado_clave' => $p->estadoClave,
                'denominacion' => $p->denominacion,
                'domicilio'    => $p->domicilio,
                'impuestos'    => $p->impuestos,
            ],
        ];
    }
}
