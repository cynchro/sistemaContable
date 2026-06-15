<?php

namespace App\Modules\Compartido\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Lectura de los catálogos AFIP globales (reference data sembrada por
 * CatalogosIvaSeeder). Son compartidos por todos los tenants y de solo lectura
 * desde la API. El mapa slug→tabla actúa de lista blanca: nunca se interpola un
 * nombre de tabla provisto por el cliente.
 */
class CatalogoRepository
{
    /** @var array<string, string> slug público => tabla física */
    private const TABLES = [
        'condiciones-iva'        => 'condiciones_iva',
        'provincias'             => 'provincias',
        'tipos-comprobante'      => 'tipos_comprobante',
        'tipos-documento'        => 'tipos_documento',
        'tipos-moneda'           => 'tipos_moneda',
        'tipos-retencion'        => 'tipos_retencion',
        'tipos-operacion-compra' => 'tipos_operacion_compra',
        'tipos-operacion-venta'  => 'tipos_operacion_venta',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listBySlug(string $slug): array
    {
        $table = self::TABLES[$slug] ?? null;

        if ($table === null) {
            throw new NotFoundException('Catalogo', $slug);
        }

        // $table proviene de la lista blanca de arriba: seguro interpolarlo.
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} ORDER BY nombre");
        $stmt->execute();

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, list<array<string, mixed>>> todos los catálogos por slug */
    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::TABLES) as $slug) {
            $out[$slug] = $this->listBySlug($slug);
        }

        return $out;
    }
}
