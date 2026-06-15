<?php

namespace App\Support;

use App\Support\Contracts\MiddlewareInterface;

class Pipeline
{
    private array $stages = [];

    public function pipe(MiddlewareInterface $middleware): static
    {
        $clone          = clone $this;
        $clone->stages[] = $middleware;
        return $clone;
    }

    public function run(Request $request, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->stages),
            fn ($carry, $middleware) => fn ($req) => $middleware->handle($req, $carry),
            $destination
        );

        return $pipeline($request);
    }
}
