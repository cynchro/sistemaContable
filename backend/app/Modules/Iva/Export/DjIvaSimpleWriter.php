<?php

namespace App\Modules\Iva\Export;

use App\Exceptions\ValidationException;
use App\Support\Calc\Decimal;

/**
 * Genera los 4 archivos CSV de "Apertura de otros conceptos" de la DJ IVA Simple
 * (Portal IVA de ARCA). Spec oficial: softContable/guia-generacion-dj/
 * LID-Ajustes-y-otros-conceptos-para-generar-la-DJ.pdf. Análisis y supuestos:
 * docs/ingenieria-inversa/dj-iva-simple-actividad.md.
 *
 * Formato común a los 4: separador de campos ';', separador decimal ',', sin
 * separador de miles, una línea por registro terminada en CRLF. Los importes se
 * imprimen con hasta 2 decimales recortando ceros a la derecha (21 / 10,5 / 1234,56),
 * tal como los ejemplos del PDF.
 *
 * Clase pura (sin DB): recibe filas ya agregadas por el repositorio y aplica los
 * mapeos de la spec (código de alícuota, tipo de sujeto comprador) y los supuestos
 * de v1 (toda la operatoria a la actividad principal; sin bienes de uso; crédito
 * fiscal con concepto 1 = compras de bienes).
 */
class DjIvaSimpleWriter
{
    /** Código de alícuota AFIP por porcentaje (campo "Código de Alícuota"). */
    private const ALICUOTA_CODES = [
        '0'    => 3,
        '2.5'  => 9,
        '5'    => 8,
        '10.5' => 4,
        '21'   => 5,
        '27'   => 6,
    ];

    /**
     * Tipo de sujeto comprador por condición de IVA del receptor:
     * 1 = Responsable Inscripto, 2 = Monotributo, 3 = Consumidor Final/Exento/No Alcanzado.
     * El cliente del exterior (condición 9) corresponde a exportación y se excluye de
     * esta DJ ("excepto exportaciones") → devuelve null para saltear la fila.
     */
    private function tipoSujeto(?int $condicionIvaId): ?int
    {
        return match ($condicionIvaId) {
            1       => 1,        // Responsable Inscripto
            3       => 2,        // Monotributo
            9       => null,     // Cliente del exterior (exportación → excluido)
            default => 3,        // RNI, Exento, Consumidor Final, No Disponible
        };
    }

    private function codigoAlicuota(?string $alicuota): int
    {
        [$entero, $frac] = explode('.', Decimal::of($alicuota ?? '0')->value(3));
        $frac = rtrim($frac, '0');
        $key  = $frac === '' ? $entero : "{$entero}.{$frac}";
        if (!isset(self::ALICUOTA_CODES[$key])) {
            throw new ValidationException([
                'alicuota' => ["Alícuota sin código AFIP en la DJ IVA Simple: '{$alicuota}'."],
            ]);
        }

        return self::ALICUOTA_CODES[$key];
    }

    /** Importe con coma decimal y sin ceros sobrantes: 21.00 → "21", 10.50 → "10,5". */
    private function monto(string $valor): string
    {
        [$entero, $frac] = explode('.', Decimal::of($valor)->value(2));
        $frac = rtrim($frac, '0');

        return $frac === '' ? $entero : "{$entero},{$frac}";
    }

    /** @param list<string> $campos */
    private function linea(array $campos): string
    {
        return implode(';', $campos) . "\r\n";
    }

    /**
     * 1) Operaciones que generan Débito Fiscal (8 campos). Tipo de operación: 1 = venta
     * de cosas muebles/obras/locaciones/servicios; 2 = venta de bienes de uso; 3 = no
     * gravado/exento. Las filas gravadas se reagrupan por tipo de sujeto + alícuota.
     *
     * @param  list<array{condicion_iva_id: ?int, alicuota: ?string, neto: string, iva: string}> $gravadoNormal
     * @param  list<array{condicion_iva_id: ?int, alicuota: ?string, neto: string, iva: string}> $gravadoBienUso
     * @param  string $noGravado total exento + no gravado del período (lado positivo)
     */
    public function debitoFiscal(
        string $actividad,
        array $gravadoNormal,
        array $gravadoBienUso,
        string $noGravado,
    ): string {
        $out  = $this->lineasGravadoDebito($actividad, $gravadoNormal, '1');
        $out .= $this->lineasGravadoDebito($actividad, $gravadoBienUso, '2');

        if (!Decimal::of($noGravado)->isZero()) {
            $out .= $this->linea([$actividad, '3', '', '', '', '', '', $this->monto($noGravado)]);
        }

        return $out;
    }

    /**
     * @param  list<array{condicion_iva_id: ?int, alicuota: ?string, neto: string, iva: string}> $gravado
     */
    private function lineasGravadoDebito(string $actividad, array $gravado, string $tipoOp): string
    {
        $out = '';
        foreach ($this->agruparPorSujeto($gravado) as $g) {
            $out .= $this->linea([
                $actividad,
                $tipoOp,
                (string) $g['tipo_sujeto'],
                (string) $g['alicuota_cod'],
                $this->monto($g['neto']),
                $this->monto($g['iva']),
                $this->monto('0'),                // Débito Fiscal Dación en Pago (no aplica)
                '',
            ]);
        }

        return $out;
    }

    /**
     * 2) Operaciones que generan Restitución de Débito Fiscal (7 campos) — notas de
     * crédito de ventas. Gravado: tipo op 1; exento/no gravado: tipo op 2.
     *
     * @param  list<array{condicion_iva_id: ?int, alicuota: ?string, neto: string, iva: string}> $gravado
     */
    public function restitucionDebito(string $actividad, array $gravado, string $noGravado): string
    {
        $out = '';
        foreach ($this->agruparPorSujeto($gravado) as $g) {
            $out .= $this->linea([
                $actividad,
                '1',
                (string) $g['tipo_sujeto'],
                (string) $g['alicuota_cod'],
                $this->monto($g['neto']),
                $this->monto($g['iva']),          // Débito Fiscal a Restituir
                '',
            ]);
        }

        if (!Decimal::of($noGravado)->isZero()) {
            $out .= $this->linea([$actividad, '2', '', '', '', '', $this->monto($noGravado)]);
        }

        return $out;
    }

    /**
     * 3) Operaciones que generan Crédito Fiscal (5 campos). Concepto por compra:
     * 1 bienes / 2 locaciones / 3 servicios / 4 inversiones de bienes de uso (default 1).
     *
     * @param  list<array{concepto?: ?int, alicuota: ?string, neto: string, iva: string, cf: string}> $gravado
     */
    public function creditoFiscal(array $gravado): string
    {
        $out = '';
        foreach ($gravado as $g) {
            $out .= $this->linea([
                $this->concepto($g['concepto'] ?? null),
                (string) $this->codigoAlicuota($g['alicuota']),
                $this->monto($g['neto']),
                $this->monto($g['iva']),          // Crédito Fiscal Facturado
                $this->monto($g['cf']),           // Crédito Fiscal Computable
            ]);
        }

        return $out;
    }

    /**
     * 4) Operaciones que generan Restitución de Crédito Fiscal (4 campos) — notas de
     * crédito de compras.
     *
     * @param  list<array{concepto?: ?int, alicuota: ?string, neto: string, iva: string, cf: string}> $gravado
     */
    public function restitucionCredito(array $gravado): string
    {
        $out = '';
        foreach ($gravado as $g) {
            $out .= $this->linea([
                $this->concepto($g['concepto'] ?? null),
                (string) $this->codigoAlicuota($g['alicuota']),
                $this->monto($g['neto']),
                $this->monto($g['iva']),          // Crédito Fiscal Facturado
            ]);
        }

        return $out;
    }

    /** Concepto del crédito fiscal (1..4); default 1 (compras de bienes). */
    private function concepto(?int $concepto): string
    {
        return (string) ($concepto !== null && $concepto >= 1 && $concepto <= 4 ? $concepto : 1);
    }

    /**
     * Reagrupa las filas gravadas (que vienen por condición de IVA + alícuota) por
     * tipo de sujeto comprador + alícuota, sumando neto e IVA. Saltea exportación.
     *
     * @param  list<array{condicion_iva_id: ?int, alicuota: ?string, neto: string, iva: string}> $gravado
     * @return list<array{tipo_sujeto: int, alicuota_cod: int, neto: string, iva: string}>
     */
    private function agruparPorSujeto(array $gravado): array
    {
        /** @var array<string, array{tipo_sujeto: int, alicuota_cod: int, neto: Decimal, iva: Decimal}> $buckets */
        $buckets = [];
        foreach ($gravado as $row) {
            $sujeto = $this->tipoSujeto($row['condicion_iva_id'] !== null ? (int) $row['condicion_iva_id'] : null);
            if ($sujeto === null) {
                continue;
            }
            $cod = $this->codigoAlicuota($row['alicuota']);
            $key = "{$sujeto}|{$cod}";
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'tipo_sujeto'  => $sujeto,
                    'alicuota_cod' => $cod,
                    'neto'         => Decimal::zero(),
                    'iva'          => Decimal::zero(),
                ];
            }
            $buckets[$key]['neto'] = $buckets[$key]['neto']->add(Decimal::of($row['neto']));
            $buckets[$key]['iva']  = $buckets[$key]['iva']->add(Decimal::of($row['iva']));
        }

        $out = [];
        foreach ($buckets as $b) {
            $out[] = [
                'tipo_sujeto'  => $b['tipo_sujeto'],
                'alicuota_cod' => $b['alicuota_cod'],
                'neto'         => $b['neto']->value(2),
                'iva'          => $b['iva']->value(2),
            ];
        }

        return $out;
    }
}
