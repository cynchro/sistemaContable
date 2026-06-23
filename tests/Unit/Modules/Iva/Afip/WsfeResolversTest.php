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
        $this->assertSame(19, CbteTipoResolver::resolve('FA', 'E'));
        $this->assertSame(51, CbteTipoResolver::resolve('FA', 'M'));
        $this->assertSame(8, CbteTipoResolver::resolve('NC', 'B'));
        $this->assertSame(12, CbteTipoResolver::resolve('ND', 'C'));
        $this->assertSame(4, CbteTipoResolver::resolve('RF', 'A'));
    }

    public function test_cbte_tipo_tickets_y_fce_mipyme(): void
    {
        // Tique Factura (controlador fiscal) — A11/guía de liquidación.
        $this->assertSame(81, CbteTipoResolver::resolve('TF', 'A'));
        $this->assertSame(82, CbteTipoResolver::resolve('TF', 'B'));
        $this->assertSame(83, CbteTipoResolver::resolve('TF', 'C'));
        // Factura de Crédito Electrónica MiPyME y sus ND/NC.
        $this->assertSame(201, CbteTipoResolver::resolve('FE', 'A'));
        $this->assertSame(206, CbteTipoResolver::resolve('FE', 'B'));
        $this->assertSame(211, CbteTipoResolver::resolve('FE', 'C'));
        $this->assertSame(202, CbteTipoResolver::resolve('DE', 'A'));
        $this->assertSame(213, CbteTipoResolver::resolve('CE', 'C'));
    }

    public function test_cbte_tipo_codigo_fijo_ignora_letra(): void
    {
        // El tipo legacy ya identifica la clase → el código no depende de la letra.
        $this->assertSame(17, CbteTipoResolver::resolve('LA', '')); // Liquid. Serv. Públicos A
        $this->assertSame(18, CbteTipoResolver::resolve('LB', 'B'));
        $this->assertSame(112, CbteTipoResolver::resolve('CA', 'A')); // NC Tique A
        $this->assertSame(114, CbteTipoResolver::resolve('CN', 'C'));
        $this->assertSame(195, CbteTipoResolver::resolve('FT', 'T')); // Factura T
        // Nota de Crédito T (id 52, comparte codigo 'NC' pero letra T).
        $this->assertSame(197, CbteTipoResolver::resolve('NC', 'T'));
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
