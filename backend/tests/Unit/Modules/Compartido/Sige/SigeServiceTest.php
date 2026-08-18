<?php

namespace Tests\Unit\Modules\Compartido\Sige;

use Tests\Unit\UnitTestCase;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Sige\ContribuyenteSige;
use App\Modules\Compartido\Sige\SigeClient;
use App\Modules\Compartido\Sige\SigeException;
use App\Modules\Compartido\Services\SigeService;

class SigeServiceTest extends UnitTestCase
{
    private function client(?ContribuyenteSige $resultado = null, ?\Throwable $throw = null): SigeClient
    {
        return new class ($resultado, $throw) implements SigeClient {
            public function __construct(private ?ContribuyenteSige $resultado, private ?\Throwable $throw)
            {
            }

            public function buscarPorCuit(string $cuit): ?ContribuyenteSige
            {
                if ($this->throw !== null) {
                    throw $this->throw;
                }
                return $this->resultado;
            }
        };
    }

    public function test_sugerencia_valida_formato_de_cuit(): void
    {
        $service = new SigeService($this->client());

        $this->expectException(ValidationException::class);
        $service->sugerencia('123');
    }

    public function test_sugerencia_normaliza_puntuacion_del_cuit_antes_de_validar(): void
    {
        $llamados = [];
        $client = new class ($llamados) implements SigeClient {
            public function __construct(private array &$llamados)
            {
            }
            public function buscarPorCuit(string $cuit): ?ContribuyenteSige
            {
                $this->llamados[] = $cuit;
                return null;
            }
        };

        (new SigeService($client))->sugerencia('20-37462532-3');

        $this->assertSame(['20374625323'], $llamados);
    }

    public function test_sugerencia_no_encontrado_no_lanza_devuelve_encontrado_false(): void
    {
        $service = new SigeService($this->client(null));

        $r = $service->sugerencia('20374625323');

        $this->assertFalse($r['encontrado']);
        $this->assertSame('20374625323', $r['cuit']);
    }

    public function test_sugerencia_propaga_la_falla_del_cliente_sin_capturarla(): void
    {
        $service = new SigeService($this->client(null, new SigeException('SIGE no responde')));

        $this->expectException(SigeException::class);
        $service->sugerencia('20374625323');
    }
}
