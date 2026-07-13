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

    public function test_lee_actividades_condicion_e_inicio_regimen_general(): void
    {
        // Régimen general: impuesto 30 (IVA) = Responsable Inscripto; actividades con
        // `orden` (1 = principal) y `periodo` (AAAAMM) → inicio de actividad.
        $personaReturn = (object) [
            'datosGenerales' => (object) [
                'tipoPersona' => 'FISICA',
                'idPersona'   => '20111111112',
                'apellido'    => 'PEREZ',
                'nombre'      => 'JUAN',
            ],
            'datosRegimenGeneral' => (object) [
                'impuesto'  => [(object) ['idImpuesto' => 30], (object) ['idImpuesto' => 34]],
                'actividad' => [
                    (object) [
                        'idActividad'          => 620100,
                        'orden'                => 2,
                        'descripcionActividad' => 'SERVICIOS DE CONSULTORES',
                        'periodo'              => '202005',
                    ],
                    (object) [
                        'idActividad'          => 620900,
                        'orden'                => 1,
                        'descripcionActividad' => 'SERVICIOS INFORMATICOS',
                        'periodo'              => '201801',
                    ],
                ],
            ],
        ];

        $p = PersonaPadron::fromSoapResponse($personaReturn);

        $this->assertSame([30, 34], $p->impuestos);
        $this->assertSame('Responsable Inscripto', $p->condicionIva);
        // Ordenadas por `orden`: la principal (orden 1) primero.
        $this->assertSame(620900, $p->actividades[0]['id']);
        $this->assertSame('SERVICIOS INFORMATICOS', $p->actividades[0]['descripcion']);
        $this->assertSame('SERVICIOS DE CONSULTORES', $p->actividades[1]['descripcion']);
        // Inicio = periodo de la principal (201801) → YYYY-MM-01.
        $this->assertSame('2018-01-01', $p->fechaInicioActividad);
    }

    public function test_a13_actividad_principal_por_campos_planos(): void
    {
        // Estructura REAL del padrón A13 (constancia de inscripción): la actividad principal
        // viene en campos planos, no en un array `actividad[]`. Verificado en vivo contra ARCA.
        $personaReturn = (object) [
            'persona' => (object) [
                'tipoPersona'                   => 'FISICA',
                'idPersona'                     => '23321452639',
                'estadoClave'                   => 'ACTIVO',
                'apellido'                      => 'SAUCEDO',
                'nombre'                        => 'ALEXIS GUSTAVO',
                'idActividadPrincipal'          => 620100,
                'descripcionActividadPrincipal' => 'SERVICIOS DE CONSULTORES EN INFORMÁTICA',
                'periodoActividadPrincipal'     => 201907,
                'domicilio'                     => (object) [
                    'tipoDomicilio'        => 'FISCAL',
                    'direccion'            => 'AV GDOR FELIPE FIGUEROA 0 Piso:1',
                    'localidad'            => 'SAN FERNANDO DEL VALLE DE CATAMARCA',
                    'idProvincia'          => 2,
                    'descripcionProvincia' => 'CATAMARCA',
                ],
            ],
        ];

        $p = PersonaPadron::fromSoapResponse($personaReturn);

        $this->assertSame('SAUCEDO ALEXIS GUSTAVO', $p->denominacion);
        $this->assertSame('CATAMARCA', $p->domicilio['provincia']);
        $this->assertCount(1, $p->actividades);
        $this->assertSame(620100, $p->actividades[0]['id']);
        $this->assertSame('SERVICIOS DE CONSULTORES EN INFORMÁTICA', $p->actividades[0]['descripcion']);
        $this->assertSame('2019-07-01', $p->fechaInicioActividad);
        // A13 no trae impuestos → sin condición IVA derivable.
        $this->assertNull($p->condicionIva);
    }

    public function test_condicion_monotributo_por_datos_monotributo(): void
    {
        $personaReturn = (object) [
            'datosGenerales'  => (object) ['tipoPersona' => 'FISICA', 'idPersona' => '20111111112'],
            'datosMonotributo' => (object) [
                'categoriaMonotributo' => (object) ['descripcionCategoria' => 'B'],
                'actividad'            => (object) [
                    'idActividad'          => 471190,
                    'orden'                => 1,
                    'descripcionActividad' => 'VENTA AL POR MENOR',
                    'periodo'              => '202101',
                ],
            ],
        ];

        $p = PersonaPadron::fromSoapResponse($personaReturn);

        $this->assertSame('Monotributo', $p->condicionIva);
        // `actividad` como objeto único también se lee.
        $this->assertSame(471190, $p->actividades[0]['id']);
        $this->assertSame('2021-01-01', $p->fechaInicioActividad);
    }

    public function test_condicion_monotributo_por_impuesto_20(): void
    {
        // A5 sin nodo datosMonotributo pero con impuesto 20/21 (Régimen Simplificado).
        $p = PersonaPadron::fromSoapResponse((object) [
            'datosGenerales'      => (object) ['tipoPersona' => 'FISICA', 'idPersona' => '20111111112'],
            'datosRegimenGeneral' => (object) ['impuesto' => [(object) ['idImpuesto' => 20]]],
        ]);

        $this->assertSame('Monotributo', $p->condicionIva);
    }

    public function test_sin_actividades_ni_impuestos_no_deriva_nada(): void
    {
        $p = PersonaPadron::fromSoapResponse((object) [
            'datosGenerales' => (object) [
                'tipoPersona' => 'JURIDICA',
                'idPersona'   => '30711111118',
                'razonSocial' => 'ACME SA',
            ],
        ]);

        $this->assertSame([], $p->actividades);
        $this->assertNull($p->condicionIva);
        $this->assertNull($p->fechaInicioActividad);
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
