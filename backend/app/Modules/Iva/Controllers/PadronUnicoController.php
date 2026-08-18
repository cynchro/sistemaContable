<?php

namespace App\Modules\Iva\Controllers;

use App\Exceptions\ValidationException;
use App\Support\Cuit;
use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\SujetoService;

/**
 * Vista global del Padrón Único de Sujetos (documento "Satélite Visual IVA" §10, Etapa 4:
 * "consulta del padrón global"). Nombre distinto de `PadronController` a propósito: ese
 * controller es la consulta al padrón de AFIP (`GET /padron/{cuit}`, no relacionado); este es
 * el padrón propio del estudio (`iva_sujetos`). A diferencia de
 * `IvaClienteController`/`IvaProveedorController` (siempre scopeados a una empresa), esta ruta
 * no cuelga de `/empresas/{id}` — lista todos los sujetos del tenant con las empresas donde cada
 * uno está activo.
 *
 * `rol` (query, `cliente`|`proveedor`) separa el padrón en dos vistas independientes — informe
 * del cliente 10/08/2026, pedido 5a: "mezclar el padrón de proveedores y el de clientes en una
 * sola integración no es posible... hacelos separados". Sin `rol`, se sigue devolviendo la vista
 * mezclada (compatibilidad), pero el frontend ya no la usa.
 */
class PadronUnicoController
{
    private const ROLES_VALIDOS = ['cliente', 'proveedor'];

    public function __construct(private SujetoService $service)
    {
    }

    public function index(Request $request): Response
    {
        $rol = $request->input('rol');
        if ($rol !== null && !in_array($rol, self::ROLES_VALIDOS, true)) {
            throw new ValidationException(['rol' => ['Tiene que ser "cliente" o "proveedor".']]);
        }

        return Response::success($this->service->listGlobal(
            (string) $request->tenantId(),
            ['q' => $request->input('q'), 'rol' => $rol],
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 50),
        ));
    }

    /**
     * "CUIT único" (informe del cliente 10/08/2026, pedido 3): antes de dar de alta una empresa
     * (contribuyente propio), el frontend consulta acá si ese CUIT ya está en el padrón de
     * sujetos (cliente/proveedor de alguna empresa del estudio), para ofrecer reusar esos datos
     * en vez de tipearlos de nuevo. Mismo shape que `EmpresaController::buscarPorCuit` /
     * `SigeController::sugerencia` (`encontrado` + datos) para reusar `useCuitLookup`.
     */
    public function porCuit(Request $request): Response
    {
        $cuit   = Cuit::normalizar((string) $request->route('cuit'));
        $sujeto = $this->service->findByCuitGlobal((string) $request->tenantId(), $cuit);

        if ($sujeto === null) {
            return Response::success(['encontrado' => false, 'cuit' => $cuit]);
        }

        return Response::success([
            'encontrado'       => true,
            'id'               => (int) $sujeto['id'],
            'nombre'           => $sujeto['nombre'],
            'cuit'             => $sujeto['cuit'],
            'domicilio'        => $sujeto['domicilio'],
            'localidad'        => $sujeto['localidad'],
            'provincia_id'     => $sujeto['provincia_id'],
            'telefono'         => $sujeto['telefono'],
            'condicion_iva_id' => $sujeto['condicion_iva_id'],
        ]);
    }
}
