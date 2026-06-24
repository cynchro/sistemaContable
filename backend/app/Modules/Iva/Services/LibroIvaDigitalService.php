<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Export\LibroIvaDigitalWriter;
use App\Modules\Iva\Repositories\LibroIvaDigitalRepository;

/**
 * Exporta los archivos del Libro IVA Digital (Portal IVA) de un período. Valida que
 * la empresa pertenezca al tenant y el período a la empresa, consulta los datos y
 * delega el formato de ancho fijo en {@see LibroIvaDigitalWriter}.
 *
 * Soporta los 4 archivos de uso común; cada uno se descarga con el nombre oficial.
 */
class LibroIvaDigitalService
{
    /** @var array<string, array{archivo: string}> slug => nombre oficial del archivo */
    private const ARCHIVOS = [
        'ventas-cbte'       => ['archivo' => 'LIBRO_IVA_DIGITAL_VENTAS_CBTE'],
        'ventas-alicuotas'  => ['archivo' => 'LIBRO_IVA_DIGITAL_VENTAS_ALICUOTAS'],
        'compras-cbte'      => ['archivo' => 'LIBRO_IVA_DIGITAL_COMPRAS_CBTE'],
        'compras-alicuotas' => ['archivo' => 'LIBRO_IVA_DIGITAL_COMPRAS_ALICUOTAS'],
    ];

    public function __construct(
        private LibroIvaDigitalRepository $datos,
        private LibroIvaDigitalWriter $writer,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
    ) {
    }

    /**
     * Genera el archivo pedido (por slug). Devuelve el nombre oficial y el contenido.
     *
     * @return array{nombre: string, contenido: string}
     */
    public function exportar(int $empresaId, int $periodoId, string $tenantId, string $slug): array
    {
        if (!isset(self::ARCHIVOS[$slug])) {
            throw new ValidationException(['archivo' => ["Archivo de Libro IVA Digital desconocido: '{$slug}'."]]);
        }

        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        $contenido = match ($slug) {
            'ventas-cbte'       => $this->writer->ventasCbte($this->datos->ventasCbte($periodoId)),
            'ventas-alicuotas'  => $this->writer->ventasAlicuotas($this->datos->ventasAlicuotas($periodoId)),
            'compras-cbte'      => $this->writer->comprasCbte($this->datos->comprasCbte($periodoId)),
            'compras-alicuotas' => $this->writer->comprasAlicuotas($this->datos->comprasAlicuotas($periodoId)),
        };

        return ['nombre' => self::ARCHIVOS[$slug]['archivo'] . '.txt', 'contenido' => $contenido];
    }
}
