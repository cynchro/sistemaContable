<?php

namespace App\Support\Contracts;

use App\Support\Container;

interface ServiceProviderInterface
{
    public function register(): void;

    public function boot(): void;
}
