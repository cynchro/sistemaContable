<?php

namespace App\Modules\Iva\Afip\Wsaa;

use RuntimeException;
use App\Modules\Iva\Afip\Soap\SoapTransport;

/**
 * Cliente del WSAA (autenticación de AFIP). Obtiene el Ticket de Acceso (TA) para un
 * servicio de negocio (p. ej. 'wsfe'):
 *
 *   TA en cache y vigente?  → se reutiliza.
 *   si no                   → arma el TRA, lo firma (CMS), llama a loginCms,
 *                             parsea el TA y lo cachea.
 *
 * Aísla los colaboradores (firma, transporte SOAP, cache) detrás de interfaces para
 * ser testeable sin red ni certificado real.
 */
class WsaaClient
{
    public function __construct(
        private CmsSigner $signer,
        private SoapTransport $transport,
        private TicketStore $store,
        private string $wsdl,
        private string $cuit,
        private int $taMargin = 600,
    ) {
    }

    public function authorize(string $service): AccessTicket
    {
        $cached = $this->store->find($this->cuit, $service);

        if ($cached !== null && !$cached->isExpired($this->taMargin)) {
            return $cached;
        }

        $tra = (new LoginTicketRequest($service))->toXml();
        $cms = $this->signer->sign($tra);

        $responseXml = $this->callLoginCms($cms);
        $ticket = AccessTicket::fromXml($responseXml);

        $this->store->save($this->cuit, $service, $ticket);

        return $ticket;
    }

    private function callLoginCms(string $cms): string
    {
        $result = $this->transport->call($this->wsdl, 'loginCms', ['in0' => $cms]);

        // ext-soap devuelve un objeto con la propiedad loginCmsReturn (el XML del TA).
        if (is_object($result) && isset($result->loginCmsReturn)) {
            return (string) $result->loginCmsReturn;
        }

        if (is_string($result)) {
            return $result;
        }

        throw new RuntimeException('Respuesta inesperada de loginCms.');
    }
}
