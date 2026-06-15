<?php

namespace App\Support\Contracts;

use App\Support\Request;
use App\Support\Auth\Principal;

interface GuardInterface
{
    /**
     * Resuelve la identidad de la petición.
     *
     * - Devuelve un Principal si la credencial es del esquema de este guard y es válida.
     * - Devuelve null si la credencial NO es de su esquema (para probar el siguiente guard).
     * - Lanza AuthException si la credencial ES de su esquema pero es inválida.
     */
    public function authenticate(Request $request): ?Principal;
}
