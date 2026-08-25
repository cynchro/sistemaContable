<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Contribuyentes\Services\CredencialService;
use App\Modules\Iva\Repositories\LiquidacionRepository;

/**
 * Orquesta el botón "Liquidar IVA": el usuario pide una liquidación (traer/subir el Libro IVA
 * Digital contra el Portal IVA de ARCA), el worker externo del bot la toma y reporta el
 * resultado. Ver plan del 25/08/2026 (`estamos-con-un-problema-composed-toucan.md`).
 */
class LiquidacionService
{
    public function __construct(
        private LiquidacionRepository $liquidaciones,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private CredencialService $credenciales,
    ) {
    }

    /** @return array<string, mixed> */
    public function list(int $empresaId, int $periodoId, string $tenantId, int $page, int $perPage): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return $this->liquidaciones->findPaginado($empresaId, $periodoId, $page, $perPage);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->assertPeriodo($empresaId, $periodoId, $tenantId);

        return $this->liquidaciones->findById($id, $empresaId);
    }

    /**
     * @param  array{direccion: string, libro: string} $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, int $periodoId, string $tenantId, int $usuarioId): array
    {
        $periodo = $this->assertPeriodo($empresaId, $periodoId, $tenantId);
        $empresa = $this->empresas->findById($empresaId, $tenantId);

        if (empty($empresa['cuit'])) {
            throw new ValidationException(['cuit' => ['La empresa no tiene un CUIT cargado.']]);
        }

        if ($this->credenciales->revealParaUsoInterno($empresaId, 'fiscal', 'AFIP', $tenantId) === null) {
            throw new ValidationException([
                'credencial' => ['Cargá la Clave Fiscal de ARCA en Contribuyentes > Credenciales antes de liquidar.'],
            ]);
        }

        if (empty($periodo['fecha_ini'])) {
            throw new ValidationException(['periodo' => ['El período no tiene fecha de inicio cargada.']]);
        }

        if ($this->liquidaciones->existeAbierta($empresaId, $periodoId)) {
            throw new ConflictException('Ya hay una liquidación en curso para este período.');
        }

        return $this->liquidaciones->create([
            'direccion'    => $data['direccion'],
            'libro'        => $data['libro'],
            // MM/YYYY: el formato que espera el bot (`--periodo`), derivado del período de
            // ecosistema en vez de pedírselo al usuario — evita que se desincronicen.
            'periodo_arca' => date('m/Y', strtotime((string) $periodo['fecha_ini'])),
        ], $empresaId, $periodoId, $usuarioId);
    }

    /**
     * El worker toma la siguiente liquidación pendiente de SU tenant (la API key del bot
     * pertenece a un único estudio). Devuelve también el `cuit` de la empresa (lo necesita el
     * bot) — NUNCA la Clave Fiscal, eso es `credencialPara()` aparte, y solo cuando el worker
     * detecta que la sesión de ese CUIT expiró.
     *
     * @return array<string, mixed>|null
     */
    public function tomarSiguientePendiente(string $tenantId): ?array
    {
        $liquidacion = $this->liquidaciones->tomarSiguientePendiente($tenantId);
        if ($liquidacion === null) {
            return null;
        }

        $empresa                       = $this->empresas->findById((int) $liquidacion['empresa_id'], $tenantId);
        $liquidacion['cuit']           = $empresa['cuit'];
        $liquidacion['empresa_nombre'] = $empresa['nombre'];

        return $liquidacion;
    }

    public function reportarEstado(int $id, string $tenantId, string $estado, ?string $resultado): void
    {
        // Confirma que existe Y pertenece al tenant de quien llama (404 si no) antes de escribir.
        $this->liquidaciones->findByIdParaTenant($id, $tenantId);
        $this->liquidaciones->actualizarEstado($id, $estado, $resultado);
    }

    /**
     * Clave Fiscal en claro para el login del bot — solo se llama cuando el worker detecta que
     * la sesión Playwright de ese CUIT expiró. `null` si la empresa de esa liquidación no tiene
     * (ya no debería pasar, `create()` lo valida, pero la credencial pudo borrarse después).
     *
     * @return array{cuit: ?string, usuario: ?string, clave: ?string}|null
     */
    public function credencialPara(int $liquidacionId, string $tenantId): ?array
    {
        $liquidacion = $this->liquidaciones->findByIdParaTenant($liquidacionId, $tenantId);
        $empresaId   = (int) $liquidacion['empresa_id'];
        $empresa     = $this->empresas->findById($empresaId, $tenantId);

        $credencial = $this->credenciales->revealParaUsoInterno($empresaId, 'fiscal', 'AFIP', $tenantId);

        return $credencial === null ? null : ['cuit' => $empresa['cuit'], ...$credencial];
    }

    /** @return array<string, mixed> período validado, dentro del tenant */
    private function assertPeriodo(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->periodos->findById($periodoId, $empresaId);
    }
}
