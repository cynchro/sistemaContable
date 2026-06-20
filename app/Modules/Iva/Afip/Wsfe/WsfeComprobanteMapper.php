<?php

namespace App\Modules\Iva\Afip\Wsfe;

/**
 * Arma el FeCAEReq de FECAESolicitar (WSFEv1) a partir del agregado Venta
 * (cabecera + discriminaciones + percepciones) y el contexto resuelto (punto de
 * venta, numero, CbteTipo, DocTipo, condición del receptor, moneda). Lógica pura,
 * sin DB ni red.
 *
 * Las percepciones integran el total (respuestas.md A1) y se emiten como
 * `Tributos → Tributo[]` (Id por {@see TributoResolver}); su importe suma a ImpTrib.
 */
class WsfeComprobanteMapper
{
    /**
     * @param  array<string, mixed> $venta agregado con clave 'discriminaciones'
     * @return array<string, mixed> FeCAEReq (FeCabReq + FeDetReq)
     */
    public function build(array $venta, FacturaContexto $ctx): array
    {
        /** @var list<array<string, mixed>> $discriminaciones */
        $discriminaciones = (array) ($venta['discriminaciones'] ?? []);

        $ivaArray = [];
        $impNeto  = 0.0;
        $impIva   = 0.0;

        foreach ($discriminaciones as $dis) {
            $base   = round((float) ($dis['neto_gravado'] ?? 0), 2);
            $imp    = round((float) ($dis['iva_importe'] ?? 0), 2);
            $impNeto += $base;
            $impIva  += $imp;

            $ivaArray[] = [
                'Id'      => AlicuotaIvaResolver::id((string) ($dis['iva_alicuota'] ?? '0')),
                'BaseImp' => $base,
                'Importe' => $imp,
            ];
        }

        // Percepciones → Tributo[] (integran el total); su importe suma a ImpTrib.
        $tributos    = [];
        $impInterno  = round((float) ($venta['imp_interno'] ?? 0), 2);
        $percepTotal = 0.0;
        foreach ((array) ($venta['percepciones'] ?? []) as $perc) {
            $importe = round((float) ($perc['importe'] ?? 0), 2);
            $percepTotal += $importe;
            $tributo = TributoResolver::fromRg3685((int) ($perc['tipo_rg3685'] ?? 0));
            $tributos[] = [
                'Id'      => $tributo['id'],
                'Desc'    => $tributo['desc'],
                'BaseImp' => round((float) ($perc['base'] ?? 0), 2),
                'Alic'    => round((float) ($perc['alicuota'] ?? 0), 2),
                'Importe' => $importe,
            ];
        }

        $impOpEx   = round((float) ($venta['exento'] ?? 0), 2);
        $impTotConc = round((float) ($venta['neto_no_grav'] ?? 0), 2);
        $impTrib   = round($impInterno + $percepTotal, 2);
        $impTotal  = round((float) ($venta['total'] ?? 0), 2);
        $concepto  = (int) ($venta['concepto'] ?? 1);

        $det = [
            'Concepto'              => $concepto,
            'DocTipo'               => $ctx->docTipo,
            'DocNro'                => (int) preg_replace('/\D/', '', (string) ($venta['cuit'] ?? '')),
            'CbteDesde'             => $ctx->numero,
            'CbteHasta'             => $ctx->numero,
            'CbteFch'               => self::fecha((string) ($venta['fecha'] ?? '')),
            'ImpTotal'              => $impTotal,
            'ImpTotConc'            => $impTotConc,
            'ImpNeto'               => round($impNeto, 2),
            'ImpOpEx'               => $impOpEx,
            'ImpTrib'               => $impTrib,
            'ImpIVA'                => round($impIva, 2),
            'MonId'                 => $ctx->monId,
            'MonCotiz'              => $ctx->monCotiz,
            'CondicionIVAReceptorId' => CondicionReceptorResolver::id($ctx->condicionCodigo),
        ];

        // El WSDL espera <Iva><AlicIva>…</AlicIva></Iva> (ArrayOfAlicIva); ídem CbtesAsoc.
        if ($ivaArray !== []) {
            $det['Iva'] = ['AlicIva' => $ivaArray];
        }

        if ($ctx->cbtesAsoc !== []) {
            $det['CbtesAsoc'] = ['CbteAsoc' => $ctx->cbtesAsoc];
        }

        // Tributos: impuestos internos (Id 4) + percepciones (Ids por TributoResolver).
        if ($impInterno > 0) {
            array_unshift($tributos, [
                'Id'      => 4,
                'Desc'    => 'Impuestos internos',
                'BaseImp' => 0.0,
                'Alic'    => 0.0,
                'Importe' => $impInterno,
            ]);
        }

        if ($tributos !== []) {
            $det['Tributos'] = ['Tributo' => $tributos];
        }

        // Conceptos 2 (servicios) y 3 (productos+servicios) requieren fechas de servicio.
        if (in_array($concepto, [2, 3], true)) {
            $det['FchServDesde'] = self::fecha((string) ($venta['fch_serv_desde'] ?? $venta['fecha'] ?? ''));
            $det['FchServHasta'] = self::fecha((string) ($venta['fch_serv_hasta'] ?? $venta['fecha'] ?? ''));
            $det['FchVtoPago']   = self::fecha((string) ($venta['fch_vto_pago'] ?? $venta['fecha'] ?? ''));
        }

        return [
            'FeCabReq' => ['CantReg' => 1, 'PtoVta' => $ctx->ptoVta, 'CbteTipo' => $ctx->cbteTipo],
            'FeDetReq' => ['FECAEDetRequest' => [$det]],
        ];
    }

    /** 'Y-m-d' (o con hora) → 'Ymd' que pide AFIP. */
    private static function fecha(string $fecha): string
    {
        $ts = strtotime($fecha);

        return $ts !== false ? date('Ymd', $ts) : date('Ymd');
    }
}
