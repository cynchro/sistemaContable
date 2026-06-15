<?php

namespace App\Support;

use App\Support\Contracts\ServiceProviderInterface;

abstract class ServiceProvider implements ServiceProviderInterface
{
    public function __construct(protected Container $container)
    {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
