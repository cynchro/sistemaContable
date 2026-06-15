<?php

namespace Tests\Unit\Modules\Cliente;

use App\Modules\Cliente\Services\ClienteService;
use App\Modules\Cliente\Repositories\ClienteRepository;
use App\Exceptions\NotFoundException;
use Tests\Unit\UnitTestCase;

class ClienteServiceTest extends UnitTestCase
{
    private ClienteService $service;
    private ClienteRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ClienteRepository::class);
        $this->service    = new ClienteService($this->repository);
    }

    public function test_get_all_returns_list(): void
    {
        $this->repository
            ->method('findAll')
            ->willReturn([['id' => 1], ['id' => 2]]);

        $result = $this->service->getAll('tenant-123');

        $this->assertCount(2, $result);
    }

    public function test_get_throws_not_found_for_missing_id(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new NotFoundException('Cliente', 99));

        $this->expectException(NotFoundException::class);
        $this->service->get(99, 'tenant-123');
    }

    public function test_delete_returns_true_on_success(): void
    {
        $this->repository
            ->method('delete')
            ->willReturn(true);

        $this->assertTrue($this->service->delete(1, 'tenant-123'));
    }
}
