<?php

namespace App\Modules\Iva\Afip\Wsfe;

/**
 * Resultado de FECAESolicitar (WSFEv1) normalizado.
 *
 * `resultado`: 'A' aprobado, 'R' rechazado, 'P' parcial. Si fue aprobado trae `cae` y
 * `caeVto` (yyyymmdd). `observaciones`/`errores` son [code => msg] devueltos por AFIP.
 */
final class ComprobanteCae
{
    /**
     * @param list<array{code:int,msg:string}> $observaciones
     * @param list<array{code:int,msg:string}> $errores
     */
    public function __construct(
        public readonly string $resultado,
        public readonly ?string $cae,
        public readonly ?string $caeVto,
        public readonly array $observaciones = [],
        public readonly array $errores = [],
    ) {
    }

    public function aprobado(): bool
    {
        return $this->resultado === 'A' && $this->cae !== null && $this->cae !== '';
    }

    /** Normaliza la respuesta SOAP de FECAESolicitar (FECAESolicitarResult). */
    public static function fromSoapResponse(mixed $resp): self
    {
        $result = self::get($resp, 'FECAESolicitarResult') ?? $resp;

        $cab    = self::get($result, 'FeCabResp');
        $detTop = self::get($result, 'FeDetResp');
        $det    = self::firstItem(self::get($detTop, 'FECAEDetResponse'));

        $resultado = (string) (self::get($det, 'Resultado') ?? self::get($cab, 'Resultado') ?? 'R');
        $cae       = self::nullable(self::get($det, 'CAE'));
        $caeVto    = self::nullable(self::get($det, 'CAEFchVto'));

        return new self(
            resultado: $resultado,
            cae: $cae,
            caeVto: $caeVto,
            observaciones: self::mensajes(self::get($det, 'Observaciones'), 'Obs'),
            errores: self::mensajes(self::get($result, 'Errors'), 'Err'),
        );
    }

    /**
     * @return list<array{code:int,msg:string}>
     */
    private static function mensajes(mixed $node, string $wrapper): array
    {
        if ($node === null) {
            return [];
        }

        // Observaciones->Obs[] / Errors->Err[]: puede ser objeto único o lista.
        $items = self::get($node, $wrapper) ?? $node;
        $items = (is_array($items) && array_is_list($items)) ? $items : [$items];

        $out = [];
        foreach ($items as $item) {
            $code = self::get($item, 'Code');
            $msg  = self::get($item, 'Msg');
            if ($code !== null || $msg !== null) {
                $out[] = ['code' => (int) $code, 'msg' => (string) $msg];
            }
        }

        return $out;
    }

    private static function firstItem(mixed $node): mixed
    {
        if (is_array($node)) {
            return array_is_list($node) ? ($node[0] ?? null) : $node;
        }

        return $node;
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
}
