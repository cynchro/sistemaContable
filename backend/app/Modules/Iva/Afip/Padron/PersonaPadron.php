<?php

namespace App\Modules\Iva\Afip\Padron;

/**
 * Datos de un contribuyente devueltos por el padrón de AFIP (ws_sr_padron_a5/a13),
 * normalizados a una forma plana y estable para autocompletar clientes/proveedores.
 *
 * Nombres de campo de origen (confirmados contra la respuesta real / pyafipws):
 * personaReturn.datosGenerales → tipoPersona, tipoClave, idPersona, estadoClave,
 * razonSocial, nombre, apellido, domicilioFiscal{direccion, localidad, codPostal,
 * idProvincia, descripcionProvincia}.
 */
final class PersonaPadron
{
    /**
     * @param array<string, mixed> $domicilio dirección normalizada
     * @param list<int>            $impuestos idImpuesto activos
     */
    public function __construct(
        public readonly string $cuit,
        public readonly string $tipoPersona,
        public readonly ?string $estadoClave,
        public readonly string $denominacion,
        public readonly array $domicilio,
        public readonly array $impuestos,
    ) {
    }

    /**
     * Normaliza el nodo `personaReturn` (o `datosGenerales`) de la respuesta SOAP.
     * Acepta un objeto (stdClass de ext-soap) o un array equivalente.
     */
    public static function fromSoapResponse(object|array $personaReturn): self
    {
        $datos = self::pick($personaReturn, 'datosGenerales') ?? $personaReturn;

        $tipoPersona = (string) (self::pick($datos, 'tipoPersona') ?? '');
        $razonSocial = self::pick($datos, 'razonSocial');
        $nombre      = self::pick($datos, 'nombre');
        $apellido    = self::pick($datos, 'apellido');

        $denominacion = $razonSocial !== null && $razonSocial !== ''
            ? (string) $razonSocial
            : trim(((string) ($apellido ?? '')) . ' ' . ((string) ($nombre ?? '')));

        return new self(
            cuit: (string) (self::pick($datos, 'idPersona') ?? ''),
            tipoPersona: $tipoPersona,
            estadoClave: self::nullableString(self::pick($datos, 'estadoClave')),
            denominacion: $denominacion,
            domicilio: self::domicilio(self::pick($datos, 'domicilioFiscal')),
            impuestos: self::impuestos(self::pick($datos, 'impuesto')),
        );
    }

    /** @return array<string, mixed> */
    private static function domicilio(mixed $dom): array
    {
        if ($dom === null) {
            return [];
        }

        // domicilioFiscal puede venir como objeto único o, en algunos alcances, como lista.
        if (is_array($dom) && array_is_list($dom)) {
            $dom = $dom[0] ?? null;
            if ($dom === null) {
                return [];
            }
        }

        return [
            'direccion'   => self::nullableString(self::pick($dom, 'direccion')),
            'localidad'   => self::nullableString(self::pick($dom, 'localidad')),
            'cod_postal'  => self::nullableString(self::pick($dom, 'codPostal')),
            'id_provincia' => self::nullableInt(self::pick($dom, 'idProvincia')),
            'provincia'   => self::nullableString(self::pick($dom, 'descripcionProvincia')),
        ];
    }

    /** @return list<int> */
    private static function impuestos(mixed $imp): array
    {
        if ($imp === null) {
            return [];
        }

        // `impuesto` puede venir como objeto único o como lista de objetos.
        $items = (is_array($imp) && array_is_list($imp)) ? $imp : [$imp];

        $ids = [];
        foreach ($items as $item) {
            $id = self::pick($item, 'idImpuesto');
            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private static function pick(mixed $node, string $key): mixed
    {
        if (is_object($node)) {
            return $node->{$key} ?? null;
        }
        if (is_array($node)) {
            return $node[$key] ?? null;
        }

        return null;
    }

    private static function nullableString(mixed $v): ?string
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }

    private static function nullableInt(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }
}
