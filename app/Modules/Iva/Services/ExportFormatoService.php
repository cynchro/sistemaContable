<?php

namespace App\Modules\Iva\Services;

use App\Support\DB;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Repositories\ExportFormatoRepository;
use App\Modules\Iva\Repositories\ReporteIvaRepository;
use App\Modules\Iva\Export\ExportTxtConfigurableWriter;
use App\Modules\Iva\Export\ExportCampoCatalogo;

/**
 * Formatos de exportación TXT configurables (CRUD por tenant) y generación del archivo
 * sobre el subdiario del período. Valida que cada campo pertenezca a la lista blanca
 * ({@see ExportCampoCatalogo}) y que el tipo/alineación sean válidos.
 */
class ExportFormatoService
{
    private const TIPOS  = ['delimitado', 'fijo'];
    private const ALINEA = ['izquierda', 'derecha'];
    private const EOL    = ['lf', 'crlf'];

    public function __construct(
        private ExportFormatoRepository $formatos,
        private ReporteIvaRepository $reportes,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private ExportTxtConfigurableWriter $writer,
        private DB $db,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listar(string $tenantId): array
    {
        return $this->formatos->findAllByTenant($tenantId);
    }

    /** @return array<string, mixed> */
    public function ver(int $id, string $tenantId): array
    {
        $formato = $this->formatos->findById($id, $tenantId);

        if ($formato === null) {
            throw new NotFoundException('Formato de exportación', $id);
        }

        return $formato;
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function crear(string $tenantId, array $input): array
    {
        $data = $this->validar($input);

        $id = $this->db->withTransaction(fn (): int => $this->formatos->create($tenantId, $data));

        return $this->ver($id, $tenantId);
    }

    public function borrar(int $id, string $tenantId): void
    {
        if (!$this->formatos->delete($id, $tenantId)) {
            throw new NotFoundException('Formato de exportación', $id);
        }
    }

    /**
     * Genera el TXT del subdiario (ventas|compras) del período según el formato.
     *
     * @return array{nombre: string, contenido: string}
     */
    public function exportar(int $empresaId, int $periodoId, string $tenantId, int $formatoId, string $tipo): array
    {
        if (!in_array($tipo, ['ventas', 'compras'], true)) {
            throw new ValidationException(['tipo' => ['Debe ser "ventas" o "compras".']]);
        }

        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        $formato = $this->ver($formatoId, $tenantId);

        $filas = $tipo === 'ventas'
            ? $this->reportes->ventas($periodoId)
            : $this->reportes->compras($periodoId);

        $filas = array_map(static function (array $f): array {
            // Normaliza el nombre de la contraparte (cliente en ventas, proveedor en compras).
            $f['contraparte_nombre'] = $f['cliente_nombre'] ?? $f['proveedor_nombre'] ?? '';

            return $f;
        }, $filas);

        return [
            'nombre'    => sprintf('%s_%s.txt', $formato['nombre'], $tipo),
            'contenido' => $this->writer->generar($formato, $filas),
        ];
    }

    /**
     * @param  array<string, mixed> $input
     * @return array{nombre: string, tipo: string, separador: string, eol: string, campos: list<array<string, mixed>>}
     */
    private function validar(array $input): array
    {
        $errores = [];

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            $errores['nombre'] = ['El nombre es obligatorio.'];
        }

        $tipo = (string) ($input['tipo'] ?? 'delimitado');
        if (!in_array($tipo, self::TIPOS, true)) {
            $errores['tipo'] = ['Debe ser "delimitado" o "fijo".'];
        }

        $eol = (string) ($input['eol'] ?? 'lf');
        if (!in_array($eol, self::EOL, true)) {
            $errores['eol'] = ['Debe ser "lf" o "crlf".'];
        }

        $campos = $input['campos'] ?? [];
        if (!is_array($campos) || $campos === []) {
            $errores['campos'] = ['Debe incluir al menos un campo.'];
            $campos = [];
        }

        foreach ($campos as $i => $campo) {
            $nombreCampo = (string) ($campo['campo'] ?? '');
            if (!ExportCampoCatalogo::existe($nombreCampo)) {
                $errores["campos.{$i}.campo"] = ["Campo no disponible: '{$nombreCampo}'."];
            }
            if (isset($campo['alinea']) && !in_array($campo['alinea'], self::ALINEA, true)) {
                $errores["campos.{$i}.alinea"] = ['Debe ser "izquierda" o "derecha".'];
            }
        }

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return [
            'nombre'    => $nombre,
            'tipo'      => $tipo,
            'separador' => (string) ($input['separador'] ?? ';'),
            'eol'       => $eol,
            'campos'    => array_values($campos),
        ];
    }
}
