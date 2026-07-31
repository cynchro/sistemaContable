<?php

namespace Tests\Feature;

use App\Modules\Iva\Repositories\VentaClasificacionRepository;

/**
 * Motor de clasificación de ventas por punto de venta + tipo de comprobante (documento "Satélite
 * Visual IVA" §4, ver documentacion/analisis-satelite-visual-iva.md §7.1.4 y §7.7 paso 5). A
 * diferencia de compras (ImputacionContableTest), acá no hay capa de "sujeto": la regla es del
 * propio punto de venta del contribuyente, con excepción opcional por tipo de comprobante (caso
 * NC vs. Factura del documento).
 */
class VentaClasificacionTest extends FeatureTestCase
{
    public function test_sin_regla_cargada_no_resuelve_ninguna_cuenta(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Clasificación SA'], $auth)['json']['data']['id'];

        $clasificacion = new VentaClasificacionRepository($this->pdo);

        $this->assertNull($clasificacion->resolverCuenta($empresaId, '0001', null));
        $this->assertNull($clasificacion->resolverCuenta($empresaId, '0001', 9));
    }

    public function test_regla_general_del_punto_de_venta(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Clasificación SA'], $auth)['json']['data']['id'];
        $cuentaId = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4001', 'nombre' => 'Ventas de Servicios',
        ], $auth)['json']['data']['id'];

        $clasificacion = new VentaClasificacionRepository($this->pdo);
        $clasificacion->setPuntoVenta($empresaId, '0003', $cuentaId);

        $this->assertSame($cuentaId, $clasificacion->resolverCuenta($empresaId, '0003', null));
        $this->assertSame($cuentaId, $clasificacion->resolverCuenta($empresaId, '0003', 9));
    }

    public function test_excepcion_por_tipo_de_comprobante_tiene_precedencia_sobre_la_regla_general(): void
    {
        // Caso del documento §4: una NC del mismo PV se imputa distinto que una Factura.
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec(
            "INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (10, 'NC', 'Nota de Crédito A', -1)"
        );

        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Clasificación SA'], $auth)['json']['data']['id'];
        $general = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4001', 'nombre' => 'Ventas de Servicios',
        ], $auth)['json']['data']['id'];
        $notasCredito = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4002', 'nombre' => 'Notas de Crédito por Ventas',
        ], $auth)['json']['data']['id'];

        $clasificacion = new VentaClasificacionRepository($this->pdo);
        $clasificacion->setPuntoVenta($empresaId, '0003', $general);
        $clasificacion->setPorTipo($empresaId, '0003', 10, $notasCredito);

        // Factura (tipo 9, sin excepción propia): cae a la regla general del PV.
        $this->assertSame($general, $clasificacion->resolverCuenta($empresaId, '0003', 9));
        // NC (tipo 10, con excepción): la excepción gana.
        $this->assertSame($notasCredito, $clasificacion->resolverCuenta($empresaId, '0003', 10));
        // Sin tipo informado: cae a la regla general.
        $this->assertSame($general, $clasificacion->resolverCuenta($empresaId, '0003', null));

        $reglas = $clasificacion->reglasPorTipo($empresaId);
        $this->assertCount(1, $reglas);
        $this->assertSame(10, (int) $reglas[0]['tipo_comprobante_id']);

        $clasificacion->deletePorTipo((int) $reglas[0]['id'], $empresaId);
        $this->assertSame($general, $clasificacion->resolverCuenta($empresaId, '0003', 10));
    }

    public function test_venta_sin_cuenta_explicita_toma_el_default_del_punto_de_venta_end_to_end(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Clasificación SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];
        $cuentaId = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4001', 'nombre' => 'Ventas de Servicios',
        ], $auth)['json']['data']['id'];

        (new VentaClasificacionRepository($this->pdo))->setPuntoVenta($empresaId, '3', $cuentaId);

        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", [
            'fecha' => '2026-01-15', 'cliente_nombre' => 'Consumidor Final',
            'letra' => 'B', 'punto_venta' => '3', 'numero' => '1',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame($cuentaId, (int) $resp['json']['data']['discriminaciones'][0]['cuenta_id']);
    }

    public function test_linea_con_cuenta_explicita_no_se_pisa_por_el_default_end_to_end(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Clasificación SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];
        $default = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4001', 'nombre' => 'Ventas de Servicios',
        ], $auth)['json']['data']['id'];
        $manual = (int) $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '4003', 'nombre' => 'Ventas de Mercadería',
        ], $auth)['json']['data']['id'];

        (new VentaClasificacionRepository($this->pdo))->setPuntoVenta($empresaId, '3', $default);

        $resp = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/ventas", [
            'fecha' => '2026-01-15', 'cliente_nombre' => 'Consumidor Final',
            'letra' => 'B', 'punto_venta' => '3', 'numero' => '1',
            'discriminaciones' => [
                ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'cuenta_id' => $manual],
            ],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $this->assertSame($manual, (int) $resp['json']['data']['discriminaciones'][0]['cuenta_id']);
    }
}
