<?php

namespace Tests\Unit\Modules\Tenant;

use App\Modules\Tenant\Services\TenantService;
use App\Modules\Tenant\Repositories\TenantRepository;
use App\Exceptions\NotFoundException;
use Tests\Unit\UnitTestCase;

class TenantServiceTest extends UnitTestCase
{
    private TenantService $service;
    private TenantRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(TenantRepository::class);
        $this->service    = new TenantService($this->repository);
    }

    public function test_get_all_returns_list(): void
    {
        $this->repository
            ->method('findAll')
            ->willReturn([
                ['id' => 'uuid-1', 'nombre' => 'Tenant A'],
                ['id' => 'uuid-2', 'nombre' => 'Tenant B'],
            ]);

        $result = $this->service->getAll();

        $this->assertCount(2, $result);
        $this->assertSame('Tenant A', $result[0]['nombre']);
    }

    public function test_get_returns_single_tenant(): void
    {
        $this->repository
            ->method('findById')
            ->with('uuid-1')
            ->willReturn(['id' => 'uuid-1', 'nombre' => 'Tenant A']);

        $result = $this->service->get('uuid-1');

        $this->assertSame('uuid-1', $result['id']);
    }

    public function test_get_throws_not_found_for_missing_id(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new NotFoundException('Tenant', 'bad-uuid'));

        $this->expectException(NotFoundException::class);
        $this->service->get('bad-uuid');
    }

    public function test_create_returns_new_id(): void
    {
        $this->repository
            ->method('create')
            ->with('New Tenant')
            ->willReturn('new-uuid-abc');

        $id = $this->service->create('New Tenant');

        $this->assertSame('new-uuid-abc', $id);
    }

    public function test_update_returns_true_on_success(): void
    {
        $this->repository
            ->method('update')
            ->with('uuid-1', 'Updated Name')
            ->willReturn(true);

        $this->assertTrue($this->service->update('uuid-1', 'Updated Name'));
    }

    public function test_update_returns_false_when_not_found(): void
    {
        $this->repository
            ->method('update')
            ->willReturn(false);

        $this->assertFalse($this->service->update('bad-uuid', 'Name'));
    }

    public function test_delete_returns_true_on_success(): void
    {
        $this->repository
            ->method('delete')
            ->with('uuid-1')
            ->willReturn(true);

        $this->assertTrue($this->service->delete('uuid-1'));
    }
}
