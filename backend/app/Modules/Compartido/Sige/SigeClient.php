<?php

namespace App\Modules\Compartido\Sige;

interface SigeClient
{
    /**
     * Busca un contribuyente por CUIT en el SIGE (sistemaCuarto).
     *
     * @throws SigeException si falla la conexión o el SIGE responde un error.
     * @return ContribuyenteSige|null null si el CUIT no existe en el SIGE (no es un error).
     */
    public function buscarPorCuit(string $cuit): ?ContribuyenteSige;
}
