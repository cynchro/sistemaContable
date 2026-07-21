<?php

namespace App\Modules\Iva\Afip\Wsfe;

/**
 * Resultado de FECompConsultar (WSFEv1) normalizado. Si el comprobante no existe en
 * AFIP, ésta NO tira una excepción de transporte: AFIP devuelve `Errors` con code 602
 * ("No se encontraron datos...") y acá se traduce a `encontrado = false`.
 */
final class ComprobanteConsultado
{
    /**
     * @param list<array{alicuota:float, base:float, importe:float}> $alicuotas
     */
    public function __construct(
        public readonly bool $encontrado,
        public readonly ?string $resultado = null,
        public readonly ?string $fecha = null,
        public readonly ?float $impTotal = null,
        public readonly ?float $impNeto = null,
        public readonly ?string $cae = null,
        public readonly ?string $caeVto = null,
        public readonly array $alicuotas = [],
    ) {
    }

    /** Normaliza la respuesta SOAP de FECompConsultar (FECompConsultarResult). */
    public static function fromSoapResponse(mixed $resp): self
    {
        $result = self::get($resp, 'FECompConsultarResult') ?? $resp;

        if (self::esNoEncontrado(self::get($result, 'Errors'))) {
            return new self(encontrado: false);
        }

        $det = self::get($result, 'ResultGet');
        if ($det === null) {
            return new self(encontrado: false);
        }

        return new self(
            encontrado: true,
            resultado: self::nullable(self::get($det, 'Resultado')),
            fecha: self::fechaIso((string) (self::get($det, 'CbteFch') ?? '')),
            impTotal: self::float(self::get($det, 'ImpTotal')),
            impNeto: self::float(self::get($det, 'ImpNeto')),
            cae: self::nullable(self::get($det, 'CodAutorizacion')),
            caeVto: self::fechaIso((string) (self::get($det, 'FchVto') ?? '')),
            alicuotas: self::alicuotas(self::get($det, 'Iva')),
        );
    }

    /**
     * `Id` en la respuesta de AFIP es el Id de alícuota WSFEv1 (no el porcentaje) — se
     * resuelve con `AlicuotaIvaResolver::porcentaje()` (mismo catálogo que usa la emisión
     * de CAE para el camino inverso). Id desconocido → 0.0 (no rompe la consulta).
     *
     * @return list<array{alicuota:float, base:float, importe:float}>
     */
    private static function alicuotas(mixed $node): array
    {
        if ($node === null) {
            return [];
        }

        $items = self::get($node, 'AlicIva') ?? $node;
        $items = (is_array($items) && array_is_list($items)) ? $items : [$items];

        $out = [];
        foreach ($items as $item) {
            $id = (int) (self::float(self::get($item, 'Id')) ?? 0);
            $out[] = [
                'alicuota' => AlicuotaIvaResolver::porcentaje($id) ?? 0.0,
                'base'     => self::float(self::get($item, 'BaseImp')) ?? 0.0,
                'importe'  => self::float(self::get($item, 'Importe')) ?? 0.0,
            ];
        }

        return $out;
    }

    private static function esNoEncontrado(mixed $errors): bool
    {
        if ($errors === null) {
            return false;
        }

        $items = self::get($errors, 'Err') ?? $errors;
        $items = (is_array($items) && array_is_list($items)) ? $items : [$items];

        foreach ($items as $item) {
            if ((int) (self::get($item, 'Code') ?? 0) === 602) {
                return true;
            }
        }

        return false;
    }

    private static function get(mixed $node, string $key): mixed
    {
        if (is_object($node)) {
            return $node->{$key} ?? null;
        }
        if (is_array($node)) {
            return $node[$key] ?? null;
        }

        return null;
    }

    private static function nullable(mixed $v): ?string
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }

    private static function float(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : (float) $v;
    }

    /** 'Ymd' de AFIP → 'Y-m-d'. */
    private static function fechaIso(string $ymd): ?string
    {
        if (!preg_match('/^\d{8}$/', $ymd)) {
            return null;
        }

        return substr($ymd, 0, 4) . '-' . substr($ymd, 4, 2) . '-' . substr($ymd, 6, 2);
    }
}
