<?php

namespace App\Modules\Tareas\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Tareas\Services\TareaService;
use App\Modules\Tareas\Requests\CreateTareaRequest;
use App\Modules\Tareas\Requests\UpdateTareaRequest;
use App\Modules\Tareas\Requests\CambiarEstadoTareaRequest;
use App\Modules\Tareas\Requests\ComentarioTareaRequest;

class TareaController
{
    public function __construct(private TareaService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list((string) $request->tenantId()));
    }

    public function show(Request $request): Response
    {
        return Response::success(
            $this->service->get((int) $request->route('id'), (string) $request->tenantId())
        );
    }

    public function create(Request $request, CreateTareaRequest $validated): Response
    {
        $tarea = $this->service->create(
            $validated->validated(),
            $this->usuarioId($request),
            (string) $request->tenantId(),
        );

        return Response::success($tarea, 201);
    }

    public function update(Request $request, UpdateTareaRequest $validated): Response
    {
        $tarea = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (string) $request->tenantId(),
        );

        return Response::success($tarea);
    }

    public function cambiarEstado(Request $request, CambiarEstadoTareaRequest $validated): Response
    {
        $tarea = $this->service->cambiarEstado(
            (int) $request->route('id'),
            (string) $validated->input('estado'),
            $validated->input('observacion') !== null ? (string) $validated->input('observacion') : null,
            $this->usuarioId($request),
            (string) $request->tenantId(),
        );

        return Response::success($tarea);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete((int) $request->route('id'), (string) $request->tenantId());

        return Response::success(['message' => 'Tarea eliminada.']);
    }

    public function comentarios(Request $request): Response
    {
        return Response::success(
            $this->service->comentarios((int) $request->route('id'), (string) $request->tenantId())
        );
    }

    public function agregarComentario(Request $request, ComentarioTareaRequest $validated): Response
    {
        return Response::success($this->service->agregarComentario(
            (int) $request->route('id'),
            (string) $validated->input('comentario'),
            $this->usuarioId($request),
            (string) $request->tenantId(),
        ));
    }

    public function historial(Request $request): Response
    {
        return Response::success(
            $this->service->historial((int) $request->route('id'), (string) $request->tenantId())
        );
    }

    private function usuarioId(Request $request): ?int
    {
        $sub = $request->user()['sub'] ?? null;

        return $sub !== null ? (int) $sub : null;
    }
}
