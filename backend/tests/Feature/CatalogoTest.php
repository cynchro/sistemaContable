<?php

namespace Tests\Feature;

/**
 * Catálogos AFIP globales (módulo Compartido): lectura por slug, índice general,
 * slug inexistente → 404 y requerimiento de autenticación.
 *
 * En la base de test el esquema se crea sin datos; el test inserta una fila para
 * verificar la lectura (en runtime los siembra CatalogosIvaSeeder).
 */
class CatalogoTest extends FeatureTestCase
{
    public function test_lista_un_catalogo_por_slug(): void
    {
        $this->pdo->exec(
            'INSERT INTO condiciones_iva (id, codigo, nombre, codigo_afip) '
            . "VALUES (1, 'RI', 'Responsable Inscripto', '01')"
        );

        $resp = $this->getJson('/catalogos/condiciones-iva', $this->bearer($this->actingAsUser()['token']));

        $this->assertSame(200, $resp['status']);
        $this->assertCount(1, $resp['json']['data']);
        $this->assertSame('Responsable Inscripto', $resp['json']['data'][0]['nombre']);
    }

    public function test_indice_devuelve_todos_los_slugs(): void
    {
        $resp = $this->getJson('/catalogos', $this->bearer($this->actingAsUser()['token']));

        $this->assertSame(200, $resp['status']);
        foreach (['condiciones-iva', 'provincias', 'tipos-comprobante', 'tipos-moneda'] as $slug) {
            $this->assertArrayHasKey($slug, $resp['json']['data']);
        }
    }

    public function test_slug_inexistente_da_404(): void
    {
        $resp = $this->getJson('/catalogos/no-existe', $this->bearer($this->actingAsUser()['token']));

        $this->assertSame(404, $resp['status']);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->assertSame(401, $this->getJson('/catalogos/provincias')['status']);
    }
}
