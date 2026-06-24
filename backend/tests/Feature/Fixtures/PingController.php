<?php

namespace Tests\Feature\Fixtures;

use App\Support\Request;
use App\Support\Response;

/**
 * Controlador mínimo para ejercitar middlewares (gating) en rutas ad-hoc de
 * test sin acoplar la prueba a que un módulo del base exponga una ruta protegida.
 */
class PingController
{
    public function index(Request $request): Response
    {
        return Response::success(['pong' => true]);
    }
}
