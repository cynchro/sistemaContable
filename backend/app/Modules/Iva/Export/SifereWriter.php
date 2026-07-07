<?php

namespace App\Modules\Iva\Export;

use App\Support\Calc\Decimal;

/**
 * Genera el TXT de "Percepciones SI.FE.RE Convenio Multilateral V4" (COMARB) a partir
 * de las percepciones de IIBB sufridas en compras de una jurisdicción. Sirve para cargar
 * en el sistema de convenio multilateral las percepciones de agentes locales que no
 * informan en COMARB (caso típico del contador: proveedor de Salta que percibe IIBB).
 *
 * Formato de ancho fijo validado byte a byte contra el ejemplo real
 * (`Percepciones SIFERE -202605 Mayo 2026.txt`), 51 caracteres por registro, CRLF:
 *   jurisdicción(3) · CUIT con guiones(13) · fecha dd/mm/aaaa(10) · punto de venta(4) ·
 *   número(8) · tipo de comprobante(2) · importe(11 = 8 enteros + ',' + 2 decimales).
 *
 * Clase pura (sin DB): recibe filas ya resueltas por el repositorio + el código de
 * jurisdicción ya resuelto por {@see JurisdiccionSifere}.
 */
class SifereWriter
{
    /**
     * @param string                     $jurisdiccion código COMARB (p. ej. '917')
     * @param list<array<string, mixed>> $percepciones filas del repositorio
     *        (fecha, punto_venta, numero, cbte_codigo, cuit, importe)
     */
    public function percepciones(string $jurisdiccion, array $percepciones): string
    {
        $lineas = array_map(fn (array $p): string => $this->linea($jurisdiccion, $p), $percepciones);

        return $lineas === [] ? '' : implode("\r\n", $lineas) . "\r\n";
    }

    /** @param array<string, mixed> $p */
    private function linea(string $jurisdiccion, array $p): string
    {
        return str_pad($jurisdiccion, 3, '0', STR_PAD_LEFT)
            . $this->cuit((string) ($p['cuit'] ?? ''))
            . $this->fecha((string) ($p['fecha'] ?? ''))
            . str_pad((string) ($p['punto_venta'] ?? ''), 4, '0', STR_PAD_LEFT)
            . str_pad((string) ($p['numero'] ?? ''), 8, '0', STR_PAD_LEFT)
            . str_pad((string) ($p['cbte_codigo'] ?? ''), 2)
            . $this->importe($p['importe'] ?? 0);
    }

    /** CUIT de 11 dígitos a "AA-BBBBBBBB-C" (13 chars). Si no tiene 11 dígitos, lo deja como viene, acotado a 13. */
    private function cuit(string $cuit): string
    {
        $d = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($d) === 11) {
            return substr($d, 0, 2) . '-' . substr($d, 2, 8) . '-' . substr($d, 10, 1);
        }

        return str_pad(substr($d, 0, 13), 13);
    }

    /** 'Y-m-d' → 'd/m/Y' (10 chars). */
    private function fecha(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        return str_pad('', 10);
    }

    /** Importe a "00003679,72" (8 enteros + ',' + 2 decimales). */
    private function importe(int|float|string|null $valor): string
    {
        $v = Decimal::of((string) ($valor ?? 0))->abs()->value(2); // "3679.72"
        [$ent, $dec] = explode('.', $v . '.0');
        $ent = substr(str_pad($ent, 8, '0', STR_PAD_LEFT), -8);

        return $ent . ',' . str_pad(substr($dec, 0, 2), 2, '0');
    }
}
