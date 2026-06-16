<?php

namespace Tests\Unit\Modules\Iva\Afip;

use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Padron\PersonaPadron;

class PersonaPadronTest extends UnitTestCase
{
    public function test_persona_juridica_con_domicilio(): void
    {
        $personaReturn = (object) [
            'datosGenerales' => (object) [
                'tipoPersona' => 'JURIDICA',
                'idPersona'   => '30711111118',
                'estadoClave' => 'ACTIVO',
                'razonSocial' => 'ACME SA',
                'domicilioFiscal' => (object) [
                    'direccion'            => 'Av. Siempreviva 742',
                    'localidad'            => 'CORDOBA',
                    'codPostal'            => '5000',
                    'idProvincia'          => 4,
                    'descripcionProvincia' => 'CORDOBA',
                ],
            ],
        ];

        $p = PersonaPadron::fromSoapResponse($personaReturn);

        $this->assertSame('30711111118', $p->cuit);
        $this->assertSame('JURIDICA', $p->tipoPersona);
        $this->assertSame('ACTIVO', $p->estadoClave);
        $this->assertSame('ACME SA', $p->denominacion);
        $this->assertSame('Av. Siempreviva 742', $p->domicilio['direccion']);
        $this->assertSame(4, $p->domicilio['id_provincia']);
        $this->assertSame('CORDOBA', $p->domicilio['provincia']);
    }

    public function test_persona_fisica_compone_apellido_nombre(): void
    {
        $personaReturn = (object) [
            'datosGenerales' => (object) [
                'tipoPersona' => 'FISICA',
                'idPersona'   => '20111111112',
                'nombre'      => 'Juan',
                'apellido'    => 'Pérez',
            ],
        ];

        $p = PersonaPadron::fromSoapResponse($personaReturn);

        $this->assertSame('Pérez Juan', $p->denominacion);
        $this->assertSame([], $p->domicilio);
        $this->assertNull($p->estadoClave);
    }

    public function test_lee_impuestos_activos_como_lista(): void
    {
        $personaReturn = (object) [
            'datosGenerales' => (object) ['tipoPersona' => 'FISICA', 'idPersona' => '20111111112'],
            'impuesto'       => [
                (object) ['idImpuesto' => 30],
                (object) ['idImpuesto' => 32],
            ],
        ];

        // `impuesto` está al nivel de personaReturn en este caso → se lee igual.
        $p = PersonaPadron::fromSoapResponse((object) [
            'tipoPersona' => 'FISICA',
            'idPersona'   => '20111111112',
            'impuesto'    => [(object) ['idImpuesto' => 30], (object) ['idImpuesto' => 32]],
        ]);

        $this->assertSame([30, 32], $p->impuestos);
    }
}
