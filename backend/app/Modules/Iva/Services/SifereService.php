<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Export\JurisdiccionSifere;
use App\Modules\Iva\Export\SifereWriter;
use App\Modules\Iva\Repositories\SifereRepository;

/**
 * Exporta el TXT de "Percepciones SI.FE.RE Convenio Multilateral V4" para una
 * jurisdicción (provincia) y período: percepciones de IIBB sufridas en compras.
 * Resuelve el código COMARB por nombre de provincia y delega el formato en
 * {@see SifereWriter}.
 */
class SifereService
{
    public function __construct(
        private SifereRepository $datos,
        private SifereWriter $writer,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
    ) {
    }

    /** @var array<string, true> tipos de export soportados */
    private const TIPOS = ['percepciones' => true];

    /**
     * @return array{nombre: string, contenido: string}
     */
    public function exportar(int $empresaId, int $periodoId, string $tenantId, string $tipo, ?int $provinciaId): array
    {
        if (!isset(self::TIPOS[$tipo])) {
            throw new ValidationException(['tipo' => ["Export SIFERE desconocido: '{$tipo}'."]]);
        }
        if ($provinciaId === null || $provinciaId <= 0) {
            throw new ValidationException(['provincia_id' => ['Indicá la jurisdicción (provincia_id).']]);
        }

        $this->empresas->findById($empresaId, $tenantId);
        $periodo = $this->periodos->findById($periodoId, $empresaId);

        $provincia = $this->datos->provincia($provinciaId);
        if ($provincia === null) {
            throw new ValidationException(['provincia_id' => ['La provincia indicada no existe.']]);
        }

        // Código COMARB: la columna `provincias.jurisdiccion` (seed legacy); si falta, se
        // resuelve por nombre con {@see JurisdiccionSifere}.
        $codigo = $provincia['jurisdiccion'] ?? JurisdiccionSifere::codigo($provincia['nombre']);
        if ($codigo === null || $codigo === '') {
            throw new ValidationException(
                ['provincia_id' => ["Sin código de jurisdicción SIFERE para '{$provincia['nombre']}'."]]
            );
        }

        $contenido = $this->writer->percepciones($codigo, $this->datos->percepcionesIibb($periodoId, $provinciaId));

        $periodoNombre = str_replace([' ', '/'], '-', (string) ($periodo['nombre'] ?? $periodoId));
        $nombre = "SIFERE_PERCEPCIONES_{$codigo}_{$periodoNombre}.txt";

        return ['nombre' => $nombre, 'contenido' => $contenido];
    }
}
