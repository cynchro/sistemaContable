<?php

namespace Tests\Unit\Modules\Admin;

use App\Modules\Admin\Services\RolService;
use App\Modules\Admin\Repositories\RolRepository;
use App\Exceptions\ValidationException;
use Tests\Unit\UnitTestCase;

class RolServiceTest extends UnitTestCase
{
    public function test_update_without_parent_skips_cycle_check(): void
    {
        $repo = $this->createMock(RolRepository::class);
        $repo->expects($this->never())->method('wouldCreateCycle');
        $repo->expects($this->once())->method('update')->with(1, 'Editor', 1, null)->willReturn(true);

        $service = new RolService($repo);

        $this->assertTrue($service->update(1, 'Editor', 1, null));
    }

    public function test_update_throws_when_parent_is_self(): void
    {
        $repo = $this->createMock(RolRepository::class);
        $repo->expects($this->never())->method('wouldCreateCycle'); // cortocircuita en self
        $repo->expects($this->never())->method('update');

        $service = new RolService($repo);

        $this->expectException(ValidationException::class);
        $service->update(5, 'Editor', 1, 5);
    }

    public function test_update_throws_when_repository_detects_cycle(): void
    {
        $repo = $this->createMock(RolRepository::class);
        $repo->method('wouldCreateCycle')->with(2, 9)->willReturn(true);
        $repo->expects($this->never())->method('update');

        $service = new RolService($repo);

        $this->expectException(ValidationException::class);
        $service->update(2, 'Editor', 1, 9);
    }

    public function test_update_persists_when_parent_is_safe(): void
    {
        $repo = $this->createMock(RolRepository::class);
        $repo->method('wouldCreateCycle')->with(2, 9)->willReturn(false);
        $repo->expects($this->once())->method('update')->with(2, 'Editor', 1, 9)->willReturn(true);

        $service = new RolService($repo);

        $this->assertTrue($service->update(2, 'Editor', 1, 9));
    }

    public function test_create_passes_parent_without_cycle_check(): void
    {
        $repo = $this->createMock(RolRepository::class);
        $repo->expects($this->never())->method('wouldCreateCycle');
        $repo->expects($this->once())->method('create')->with('Editor', 3)->willReturn(42);

        $service = new RolService($repo);

        $this->assertSame(42, $service->create('Editor', 3));
    }
}
