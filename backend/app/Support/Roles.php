<?php

namespace App\Support;

final class Roles
{
    public const ADMIN = 1;
    public const USER  = 0;

    /**
     * Permiso "super-admin": cualquier rol que lo tenga asignado se considera
     * administrador. Desacopla el gate de admin del id de rol hardcodeado.
     */
    public const SUPER_PERMISSION = 'Acceso Total';
}
