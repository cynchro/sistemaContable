<?php

namespace Tests\Feature;

/**
 * Listado de ventas paginado y filtrado (§G): el index acepta page/per_page y filtros
 * (fecha_desde/fecha_hasta/cliente_id/letra) por query string, y devuelve el formato
 * paginado (total / cantidad_por_pagina / pagina / results).
 */
class VentaListadoTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, base: string} */
    private function escenarioCon3Ventas(): array
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura', 1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'RI')");

        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'Listado SA'], $auth)['json']['data']['id'];
        $p = (int) $this->postJson("/empresas/{$e}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];
        $base = "/empresas/{$e}/periodos/{$p}";

        $datos = [['2026-01-05', 'A', '1'], ['2026-01-15', 'A', '2'], ['2026-01-25', 'B', '3']];
        foreach ($datos as [$fecha, $letra, $nro]) {
            $this->postJson("{$base}/ventas", [
                'fecha' => $fecha, 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
                'letra' => $letra, 'punto_venta' => '1', 'numero' => $nro,
                'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
            ], $auth);
        }

        return ['auth' => $auth, 'base' => $base];
    }

    public function test_paginacion(): void
    {
        ['auth' => $auth, 'base' => $base] = $this->escenarioCon3Ventas();

        $p1 = $this->getJson("{$base}/ventas?per_page=2&page=1", $auth);
        $this->assertSame(200, $p1['status']);
        $this->assertSame(3, $p1['json']['data']['total']);
        $this->assertSame(2, $p1['json']['data']['cantidad_por_pagina']);
        $this->assertCount(2, $p1['json']['data']['results']);

        $p2 = $this->getJson("{$base}/ventas?per_page=2&page=2", $auth);
        $this->assertCount(1, $p2['json']['data']['results']);
    }

    public function test_filtro_por_fecha_y_letra(): void
    {
        ['auth' => $auth, 'base' => $base] = $this->escenarioCon3Ventas();

        // fecha_desde 2026-01-20 → sólo la del 25.
        $porFecha = $this->getJson("{$base}/ventas?fecha_desde=2026-01-20", $auth);
        $this->assertSame(1, $porFecha['json']['data']['total']);

        // letra A → las dos primeras.
        $porLetra = $this->getJson("{$base}/ventas?letra=A", $auth);
        $this->assertSame(2, $porLetra['json']['data']['total']);
    }
}
