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
     * @param array<string, mixed>                                                  $domicilio    dirección normalizada
     * @param list<int>                                                             $impuestos    idImpuesto activos
     * @param list<array{id:int, orden:?int, descripcion:?string, periodo:?string}> $actividades  orden 1 = principal
     */
    public function __construct(
        public readonly string $cuit,
        public readonly string $tipoPersona,
        public readonly ?string $estadoClave,
        public readonly string $denominacion,
        public readonly array $domicilio,
        public readonly array $impuestos,
        public readonly array $actividades = [],
        public readonly ?string $condicionIva = null,
        public readonly ?string $fechaInicioActividad = null,
    ) {
    }

    /**
     * Normaliza el nodo `personaReturn` (o `datosGenerales`) de la respuesta SOAP.
     * Acepta un objeto (stdClass de ext-soap) o un array equivalente.
     */
    public static function fromSoapResponse(object|array $personaReturn): self
    {
        // A5/A4/A10 anidan en `datosGenerales`; A13 (constancia de inscripción) en `persona`.
        $datos = self::pick($personaReturn, 'datosGenerales')
            ?? self::pick($personaReturn, 'persona')
            ?? $personaReturn;

        $tipoPersona = (string) (self::pick($datos, 'tipoPersona') ?? '');
        $razonSocial = self::pick($datos, 'razonSocial');
        $nombre      = self::pick($datos, 'nombre');
        $apellido    = self::pick($datos, 'apellido');

        $denominacion = $razonSocial !== null && $razonSocial !== ''
            ? (string) $razonSocial
            : trim(((string) ($apellido ?? '')) . ' ' . ((string) ($nombre ?? '')));

        // Domicilio: A5 lo trae en `domicilioFiscal` (uno); A13 en `domicilio` (lista → el fiscal).
        $dom = self::pick($datos, 'domicilioFiscal') ?? self::pick($datos, 'domicilio');

        // Impuestos, actividades y régimen viven fuera de `datosGenerales` (en
        // `datosRegimenGeneral`/`datosMonotributo`, o al ras de `personaReturn`).
        $impuestos   = self::impuestos(self::pickEn($personaReturn, 'impuesto'));
        $actividades = self::actividades($personaReturn);

        return new self(
            cuit: (string) (self::pick($datos, 'idPersona') ?? ''),
            tipoPersona: $tipoPersona,
            estadoClave: self::nullableString(self::pick($datos, 'estadoClave')),
            denominacion: $denominacion,
            domicilio: self::domicilio($dom),
            impuestos: $impuestos,
            actividades: $actividades,
            condicionIva: self::condicionIva($personaReturn, $impuestos),
            fechaInicioActividad: self::inicioActividad($actividades),
        );
    }

    /**
     * Busca una clave en `personaReturn` mirando primero `datosGenerales`/`persona`,
     * después `datosRegimenGeneral`/`datosMonotributo` y por último al ras. Devuelve el
     * primer valor no nulo (permite leer impuestos/actividades que ARCA anida distinto
     * según régimen general vs. monotributo).
     */
    private static function pickEn(object|array $root, string $key): mixed
    {
        foreach (['datosGenerales', 'datosRegimenGeneral', 'datosMonotributo', 'persona'] as $sub) {
            $node = self::pick($root, $sub);
            if ($node !== null) {
                $val = self::pick($node, $key);
                if ($val !== null) {
                    return $val;
                }
            }
        }

        return self::pick($root, $key);
    }

    /**
     * Actividades declaradas, ordenadas por `orden` (1 = principal). Cubre las dos formas
     * de ARCA: el array `actividad[]` (padrón A5) y los campos planos de la actividad
     * principal (`idActividadPrincipal`/`descripcionActividadPrincipal`/
     * `periodoActividadPrincipal`) de la constancia de inscripción (A13).
     *
     * @return list<array{id:int, orden:?int, descripcion:?string, periodo:?string}>
     */
    private static function actividades(object|array $root): array
    {
        $raw = self::pickEn($root, 'actividad');
        if ($raw === null) {
            return self::actividadPrincipalPlana($root);
        }

        $items = (is_array($raw) && array_is_list($raw)) ? $raw : [$raw];

        $out = [];
        foreach ($items as $item) {
            $id = self::pick($item, 'idActividad');
            if ($id === null) {
                continue;
            }
            $out[] = [
                'id'          => (int) $id,
                'orden'       => self::nullableInt(self::pick($item, 'orden')),
                'descripcion' => self::nullableString(self::pick($item, 'descripcionActividad')),
                'periodo'     => self::nullableString(self::pick($item, 'periodo')),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return ($a['orden'] ?? PHP_INT_MAX) <=> ($b['orden'] ?? PHP_INT_MAX);
        });

        return $out;
    }

    /**
     * Actividad principal de la constancia A13 (campos planos, sin array `actividad[]`).
     *
     * @return list<array{id:int, orden:?int, descripcion:?string, periodo:?string}>
     */
    private static function actividadPrincipalPlana(object|array $root): array
    {
        $id = self::pickEn($root, 'idActividadPrincipal');
        if ($id === null) {
            return [];
        }

        return [[
            'id'          => (int) $id,
            'orden'       => 1,
            'descripcion' => self::nullableString(self::pickEn($root, 'descripcionActividadPrincipal')),
            'periodo'     => self::nullableString(self::pickEn($root, 'periodoActividadPrincipal')),
        ]];
    }

    /**
     * Deriva la condición frente al IVA a partir del régimen y los impuestos activos.
     * ARCA no la devuelve como texto: se infiere (Monotributo si hay `datosMonotributo`;
     * si no, IVA 30 = Responsable Inscripto, 32 = Exento).
     *
     * @param list<int> $impuestos
     */
    private static function condicionIva(object|array $root, array $impuestos): ?string
    {
        if (self::pick($root, 'datosMonotributo') !== null) {
            return 'Monotributo';
        }
        if (in_array(30, $impuestos, true)) {
            return 'Responsable Inscripto';
        }
        if (in_array(32, $impuestos, true)) {
            return 'IVA Exento';
        }

        return null;
    }

    /**
     * Fecha de inicio de actividad: `periodo` (AAAAMM) de la actividad principal
     * normalizado a `YYYY-MM-01`.
     *
     * @param list<array{id:int, orden:?int, descripcion:?string, periodo:?string}> $actividades
     */
    private static function inicioActividad(array $actividades): ?string
    {
        $periodo = $actividades[0]['periodo'] ?? null;
        if ($periodo === null || !preg_match('/^(\d{4})(\d{2})$/', $periodo, $m)) {
            return null;
        }

        return "{$m[1]}-{$m[2]}-01";
    }

    /** @return array<string, mixed> */
    private static function domicilio(mixed $dom): array
    {
        if ($dom === null) {
            return [];
        }

        // Puede venir como objeto único o como lista (A13: `domicilio[]`). De la lista se
        // prefiere el domicilio FISCAL; si no hay marca, el primero.
        if (is_array($dom) && array_is_list($dom)) {
            $dom = self::domicilioFiscalDeLista($dom);
            if ($dom === null) {
                return [];
            }
        }

        return [
            'direccion'   => self::nullableString(self::pick($dom, 'direccion')),
            'localidad'   => self::nullableString(self::pick($dom, 'localidad')),
            // A5 usa `codPostal`; A13 usa `codigoPostal`.
            'cod_postal'  => self::nullableString(self::pick($dom, 'codPostal') ?? self::pick($dom, 'codigoPostal')),
            'id_provincia' => self::nullableInt(self::pick($dom, 'idProvincia')),
            'provincia'   => self::nullableString(self::pick($dom, 'descripcionProvincia')),
        ];
    }

    /**
     * De una lista de domicilios (A13), devuelve el marcado como FISCAL; si ninguno lo
     * está, el primero.
     *
     * @param list<mixed> $lista
     */
    private static function domicilioFiscalDeLista(array $lista): mixed
    {
        foreach ($lista as $item) {
            $tipo = strtoupper((string) (self::pick($item, 'tipoDomicilio') ?? ''));
            if (str_contains($tipo, 'FISCAL')) {
                return $item;
            }
        }

        return $lista[0] ?? null;
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
