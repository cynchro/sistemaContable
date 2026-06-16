<?php

namespace App\Modules\Iva\Afip\Soap;

/**
 * Transporte SOAP para los web services de AFIP. Aísla la dependencia de ext-soap
 * detrás de una interfaz, de modo que la lógica de WSAA/WSFE sea testeable con un
 * doble (sin red ni extensión soap).
 */
interface SoapTransport
{
    /**
     * Invoca un método SOAP y devuelve el valor de retorno crudo.
     *
     * @param  array<string, mixed> $args argumentos del método (mapa nombre→valor)
     * @return mixed
     */
    public function call(string $wsdl, string $method, array $args): mixed;
}
