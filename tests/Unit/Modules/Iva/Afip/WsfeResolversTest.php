<?php

namespace Tests\Unit\Modules\Iva\Afip;

use RuntimeException;
use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsfe\CbteTipoResolver;
use App\Modules\Iva\Afip\Wsfe\AlicuotaIvaResolver;
use App\Modules\Iva\Afip\Wsfe\CondicionReceptorResolver;

class WsfeResolversTest extends UnitTestCase
{
    public function test_cbte_tipo_segun_tipo_y_letra(): void
    {
        $this->assertSame(1, CbteTipoResolver::resolve('FA', 'A'));
        $this->assertSame(6, CbteTipoResolver::resolve('FA', 'B'));
        $this->assertSame(11, CbteTipoResolver::resolve('FA', 'C'));
        $this->assertSame(8, CbteTipoResolver::resolve('NC', 'B'));
        $this->assertSame(12, CbteTipoResolver::resolve('ND', 'C'));
        $this->assertSame(4, CbteTipoResolver::resolve('RF', 'A'));
    }

    public function test_cbte_tipo_desconocido_lanza(): void
    {
        $this->expectException(RuntimeException::class);
        CbteTipoResolver::resolve('XX', 'A');
    }

    public function test_alicuota_iva_id(): void
    {
        $this->assertSame(5, AlicuotaIvaResolver::id('21.000'));
        $this->assertSame(4, AlicuotaIvaResolver::id('10.500'));
        $this->assertSame(3, AlicuotaIvaResolver::id('0'));
        $this->assertSame(5, AlicuotaIvaResolver::id(21));
        $this->assertSame(9, AlicuotaIvaResolver::id(2.5));
    }

    public function test_alicuota_desconocida_lanza(): void
    {
        $this->expectException(RuntimeException::class);
        AlicuotaIvaResolver::id('17');
    }

    public function test_condicion_receptor_id(): void
    {
        $this->assertSame(1, CondicionReceptorResolver::id('RI'));
        $this->assertSame(5, CondicionReceptorResolver::id('CF'));
        $this->assertSame(6, CondicionReceptorResolver::id('MO'));
        $this->assertSame(9, CondicionReceptorResolver::id('CE'));
    }

    public function test_condicion_sin_informacion_default_consumidor_final(): void
    {
        // A8: sin datos del receptor → Consumidor Final (5), no se lanza.
        $this->assertSame(5, CondicionReceptorResolver::id('ND')); // No Disponible
        $this->assertSame(5, CondicionReceptorResolver::id(''));   // vacío
        $this->assertSame(5, CondicionReceptorResolver::id('RN')); // RNI eliminado / desconocido
    }
}
