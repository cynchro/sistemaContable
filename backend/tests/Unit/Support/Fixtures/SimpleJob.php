<?php

namespace Tests\Unit\Support\Fixtures;

use App\Support\Container;
use App\Support\Job;

class SimpleJob extends Job
{
    public string $email = '';
    public int $userId = 0;

    public function handle(Container $container): void
    {
    }
}
