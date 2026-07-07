<?php

namespace Tests\Unit\Modules\Iva;

use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Export\SifereWriter;
use App\Modules\Iva\Export\JurisdiccionSifere;

class SifereWriterTest extends UnitTestCase
{
    /** @return array<string, string> */
    private function row(string $fecha, string $pv, string $nro, string $cuit, string $imp): array
    {
        return [
            'fecha'       => $fecha,
            'punto_venta' => $pv,
            'numero'      => $nro,
            'cuit'        => $cuit,
            'cbte_codigo' => 'FA',
            'importe'     => $imp,
        ];
    }

    /** Reproduce byte a byte el ejemplo real del contador (Percepciones SIFERE 202605, Salta). */
    public function test_percepciones_byte_a_byte_contra_ejemplo_real(): void
    {
        $a = '30633358202';
        $b = '30661162445';
        $filas = [
            $this->row('2026-05-07', '3', '1441462', $a, '3679.72'),
            $this->row('2026-05-07', '3', '1441463', $a, '354.61'),
            $this->row('2026-05-19', '3', '1444936', $a, '2190.51'),
            $this->row('2026-05-19', '3', '1445103', $a, '313.05'),
            $this->row('2026-05-05', '7', '115945', $b, '4129.19'),
            $this->row('2026-05-18', '7', '116384', $b, '211630.05'),
            $this->row('2026-05-28', '7', '116788', $b, '1944.00'),
            $this->row('2026-05-29', '7', '117048', $b, '138996.00'),
        ];

        $esperado = "91730-63335820-207/05/2026000301441462FA00003679,72\r\n"
            . "91730-63335820-207/05/2026000301441463FA00000354,61\r\n"
            . "91730-63335820-219/05/2026000301444936FA00002190,51\r\n"
            . "91730-63335820-219/05/2026000301445103FA00000313,05\r\n"
            . "91730-66116244-505/05/2026000700115945FA00004129,19\r\n"
            . "91730-66116244-518/05/2026000700116384FA00211630,05\r\n"
            . "91730-66116244-528/05/2026000700116788FA00001944,00\r\n"
            . "91730-66116244-529/05/2026000700117048FA00138996,00\r\n";

        $this->assertSame($esperado, (new SifereWriter())->percepciones('917', $filas));
    }

    public function test_cada_registro_mide_51_caracteres(): void
    {
        $out = (new SifereWriter())->percepciones('917', [
            $this->row('2026-05-07', '3', '1441462', '30633358202', '3679.72'),
        ]);

        $this->assertSame(51, strlen(rtrim($out, "\r\n")));
    }

    public function test_sin_percepciones_devuelve_vacio(): void
    {
        $this->assertSame('', (new SifereWriter())->percepciones('917', []));
    }

    public function test_jurisdiccion_por_nombre(): void
    {
        $this->assertSame('917', JurisdiccionSifere::codigo('Salta'));
        $this->assertSame('903', JurisdiccionSifere::codigo('Catamarca'));
        $this->assertSame('901', JurisdiccionSifere::codigo('Capital Federal'));
        $this->assertSame('921', JurisdiccionSifere::codigo('Santa Fe'));
        $this->assertNull(JurisdiccionSifere::codigo('Provincia Inexistente'));
    }
}
