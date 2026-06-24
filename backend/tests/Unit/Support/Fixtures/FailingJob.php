<?php

namespace Tests\Unit\Support\Fixtures;

use App\Support\Container;
use App\Support\Job;

class FailingJob extends Job
{
    public function handle(Container $container): void
    {
        throw new \RuntimeException('intentional failure');
    }
}
