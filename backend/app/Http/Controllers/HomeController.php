<?php

namespace App\Http\Controllers;

use App\Support\Response;

class HomeController
{
    public function index(): Response
    {
        return Response::success([
            'name'   => 'Modux API',
            'health' => '/health',
        ]);
    }
}
