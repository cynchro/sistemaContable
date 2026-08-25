<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Exceptions\NotFoundException;
use App\Modules\Iva\Services\LiquidacionService;
use App\Modules\Iva\Requests\CreateLiquidacionRequest;
use App\Modules\Iva\Requests\ReportarEstadoLiquidacionRequest;

/**
 * Botón "Liquidar IVA": endpoints de usuario (crear el pedido, ver estado/historial) y
 * endpoints del worker externo del bot (tomar el siguiente pendiente, reportar resultado, pedir
 * la Clave Fiscal solo cuando hace falta). Ver plan del 25/08/2026.
 */
class LiquidacionController
{
    public function __construct(private LiquidacionService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 20),
        ));
    }

    public function show(Request $request): Response
    {
        return Response::success($this->service->get(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    public function create(Request $request, CreateLiquidacionRequest $validated): Response
    {
        $user = $request->user();

        $liquidacion = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (int) ($user['sub'] ?? 0),
        );

        return Response::success($liquidacion, 201);
    }

    /**
     * Worker: toma la siguiente liquidación pendiente del tenant de la API key. `liquidacion`
     * viene `null` cuando no hay nada pendiente (respuesta 200 igual, no es un error).
     */
    public function pendiente(Request $request): Response
    {
        $liquidacion = $this->service->tomarSiguientePendiente((string) $request->tenantId());

        return Response::success(['liquidacion' => $liquidacion]);
    }

    /** Worker: reporta el progreso/resultado de una liquidación ya tomada. */
    public function estado(Request $request, ReportarEstadoLiquidacionRequest $validated): Response
    {
        $data      = $validated->validated();
        $resultado = $data['resultado'] ?? null;

        $this->service->reportarEstado(
            (int) $request->route('id'),
            (string) $request->tenantId(),
            $data['estado'],
            $resultado === null || is_string($resultado) ? $resultado : json_encode($resultado),
        );

        return Response::success(['message' => 'Estado actualizado.']);
    }

    /**
     * Worker: pide la Clave Fiscal en claro para el login — solo cuando la sesión Playwright de
     * ese CUIT expiró. Nunca se cachea acá, se usa una vez y se descarta del lado del bot.
     */
    public function credencial(Request $request): Response
    {
        $credencial = $this->service->credencialPara(
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        if ($credencial === null) {
            throw new NotFoundException('Credencial fiscal', (int) $request->route('id'));
        }

        return Response::success($credencial);
    }
}
