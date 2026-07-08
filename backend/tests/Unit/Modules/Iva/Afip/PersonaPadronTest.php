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

    public function test_a13_lee_nodo_persona_y_domicilio_fiscal_de_la_lista(): void
    {
        // Estructura real del padrón A13: personaReturn.persona + domicilio[] (elige el FISCAL,
        // usa codigoPostal). Verificado en vivo contra ARCA (SAUCEDO ALEXIS GUSTAVO).
        $personaReturn = (object) [
            'metadata' => (object) ['servidor' => 'x'],
            'persona'  => (object) [
                'tipoPersona' => 'FISICA',
                'idPersona'   => '23321452639',
                'estadoClave' => 'ACTIVO',
                'apellido'    => 'SAUCEDO',
                'nombre'      => 'ALEXIS GUSTAVO',
                'domicilio'   => [
                    (object) [
                        'tipoDomicilio'        => 'LEGAL/REAL',
                        'direccion'            => 'OTRO 1',
                        'localidad'            => 'OTRA',
                        'idProvincia'          => 1,
                    ],
                    (object) [
                        'tipoDomicilio'        => 'FISCAL',
                        'direccion'            => 'AV GDOR FELIPE FIGUEROA 0 Piso:1',
                        'localidad'            => 'SAN FERNANDO DEL VALLE DE CATAMARCA',
                        'codigoPostal'         => '4700',
                        'idProvincia'          => 2,
                        'descripcionProvincia' => 'CATAMARCA',
                    ],
                ],
            ],
        ];

        $p = PersonaPadron::fromSoapResponse($personaReturn);

        $this->assertSame('23321452639', $p->cuit);
        $this->assertSame('SAUCEDO ALEXIS GUSTAVO', $p->denominacion);
        $this->assertSame('ACTIVO', $p->estadoClave);
        // Toma el domicilio FISCAL de la lista, no el primero.
        $this->assertSame('AV GDOR FELIPE FIGUEROA 0 Piso:1', $p->domicilio['direccion']);
        $this->assertSame('4700', $p->domicilio['cod_postal']);
        $this->assertSame(2, $p->domicilio['id_provincia']);
        $this->assertSame('CATAMARCA', $p->domicilio['provincia']);
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
