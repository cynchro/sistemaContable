<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Export\DjIvaSimpleWriter;
use App\Modules\Iva\Repositories\DjIvaSimpleRepository;

/**
 * Exporta los 4 archivos de "Apertura de otros conceptos" de la DJ IVA Simple
 * (Portal IVA). Valida empresa→tenant y período→empresa, distribuye la operatoria
 * del período por actividad (supuesto v1: la actividad principal de la empresa) y
 * delega el formato CSV en {@see DjIvaSimpleWriter}.
 *
 * Análisis y supuestos: docs/ingenieria-inversa/dj-iva-simple-actividad.md.
 */
class DjIvaSimpleService
{
    /** @var array<string, string> slug => nombre del archivo */
    private const ARCHIVOS = [
        'debito-fiscal'        => 'DJ_IVA_SIMPLE_DEBITO_FISCAL',
        'restitucion-debito'   => 'DJ_IVA_SIMPLE_RESTITUCION_DEBITO',
        'credito-fiscal'       => 'DJ_IVA_SIMPLE_CREDITO_FISCAL',
        'restitucion-credito'  => 'DJ_IVA_SIMPLE_RESTITUCION_CREDITO',
    ];

    public function __construct(
        private DjIvaSimpleRepository $datos,
        private DjIvaSimpleWriter $writer,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
    ) {
    }

    /**
     * Genera el archivo pedido (por slug). Devuelve nombre y contenido CSV.
     *
     * @return array{nombre: string, contenido: string}
     */
    public function exportar(int $empresaId, int $periodoId, string $tenantId, string $slug): array
    {
        if (!isset(self::ARCHIVOS[$slug])) {
            throw new ValidationException(['archivo' => ["Archivo de DJ IVA Simple desconocido: '{$slug}'."]]);
        }

        $empresa = $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        $contenido = match ($slug) {
            'debito-fiscal' => $this->writer->debitoFiscal(
                $this->actividad($empresa),
                $this->datos->ventasGravado($periodoId, 1),
                $this->datos->ventasNoGravado($periodoId, 1),
            ),
            'restitucion-debito' => $this->writer->restitucionDebito(
                $this->actividad($empresa),
                $this->datos->ventasGravado($periodoId, -1),
                $this->datos->ventasNoGravado($periodoId, -1),
            ),
            'credito-fiscal' => $this->writer->creditoFiscal(
                $this->datos->comprasGravado($periodoId, 1),
            ),
            'restitucion-credito' => $this->writer->restitucionCredito(
                $this->datos->comprasGravado($periodoId, -1),
            ),
        };

        return ['nombre' => self::ARCHIVOS[$slug] . '.csv', 'contenido' => $contenido];
    }

    /**
     * Código de actividad principal de la empresa (campo "Actividad" de la DJ). En
     * v1 toda la operatoria de débito se imputa a esta actividad; sin ella no se
     * puede generar el archivo de débito/restitución.
     *
     * @param array<string, mixed> $empresa
     */
    private function actividad(array $empresa): string
    {
        $actividad = $empresa['actividad1_id'] ?? null;
        if ($actividad === null || (string) $actividad === '') {
            throw new ValidationException([
                'actividad1_id' => ['La empresa no tiene actividad principal cargada (requerida por la DJ).'],
            ]);
        }

        return (string) $actividad;
    }
}
