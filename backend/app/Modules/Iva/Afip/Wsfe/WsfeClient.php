<?php

namespace App\Modules\Iva\Afip\Wsfe;

/**
 * Cliente de WSFEv1 (factura electrónica). Implementaciones se autentican vía WSAA
 * (servicio 'wsfe'). Interfaz para sustituir en tests sin red ni certificado.
 */
interface WsfeClient
{
    /** Healthcheck del servicio (FEDummy): ['appserver'=>..,'dbserver'=>..,'authserver'=>..]. */
    public function dummy(): array;

    /** Último número de comprobante autorizado para (punto de venta, tipo). */
    public function ultimoAutorizado(int $ptoVta, int $cbteTipo): int;

    /**
     * Solicita el CAE para un comprobante.
     *
     * @param array<string, mixed> $feCaeReq estructura FeCAEReq (FeCabReq + FeDetReq)
     */
    public function solicitarCae(array $feCaeReq): ComprobanteCae;

    /** Consulta un comprobante puntual ya registrado en AFIP (FECompConsultar). */
    public function consultarComprobante(int $ptoVta, int $cbteTipo, int $cbteNro): ComprobanteConsultado;
}
