<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\CompraService;
use App\Modules\Iva\Requests\CreateCompraRequest;
use App\Modules\Iva\Requests\UpdateCompraRequest;
use App\Modules\Iva\Requests\MoverComprobanteRequest;

class CompraController
{
    public function __construct(private CompraService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            [
                'fecha_desde'  => $request->input('fecha_desde'),
                'fecha_hasta'  => $request->input('fecha_hasta'),
                'proveedor_id' => $request->input('proveedor_id'),
                'cuit'         => $request->input('cuit'),
                'letra'        => $request->input('letra'),
                'nombre'       => $request->input('nombre'),
                'numero'       => $request->input('numero'),
                'orden'        => $request->input('orden'),
            ],
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 50),
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

    public function create(Request $request, CreateCompraRequest $validated): Response
    {
        $compra = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        );

        return Response::success($compra, 201);
    }

    public function update(Request $request, UpdateCompraRequest $validated): Response
    {
        $compra = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        );

        return Response::success($compra);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Compra eliminada.']);
    }

    public function mover(Request $request, MoverComprobanteRequest $validated): Response
    {
        $compra = $this->service->mover(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (int) $validated->validated()['periodo_destino_id'],
            (string) $request->tenantId(),
        );

        return Response::success($compra);
    }

    /**
     * Importación masiva: recibe `comprobantes` (arreglo de cabeceras como las de create)
     * y crea cada uno reusando el motor/validaciones del service. Es resiliente: cada fila
     * va en su propia transacción y los errores se reportan por índice sin abortar el resto.
     */
    public function import(Request $request): Response
    {
        $comprobantes = $request->input('comprobantes');
        if (!is_array($comprobantes)) {
            return Response::error('Se espera un arreglo "comprobantes".');
        }

        $empresaId = (int) $request->route('empresaId');
        $periodoId = (int) $request->route('periodoId');
        $tenantId  = (string) $request->tenantId();

        $creados = 0;
        $errores = [];
        foreach (array_values($comprobantes) as $i => $data) {
            if (!is_array($data)) {
                $errores[] = ['fila' => $i, 'error' => 'Fila inválida.'];
                continue;
            }
            try {
                /** @var array<string, mixed> $data */
                // Descarta nulls (como hace FormRequest::validated en el alta normal) para
                // que las columnas NOT NULL con DEFAULT tomen su valor por defecto.
                $limpio = array_filter($data, static fn ($v) => $v !== null);
                $this->service->create($limpio, $empresaId, $periodoId, $tenantId);
                $creados++;
            } catch (\Throwable $e) {
                $errores[] = ['fila' => $i, 'error' => $e->getMessage()];
            }
        }

        return Response::success([
            'total'   => count($comprobantes),
            'creados' => $creados,
            'errores' => $errores,
        ]);
    }
}
