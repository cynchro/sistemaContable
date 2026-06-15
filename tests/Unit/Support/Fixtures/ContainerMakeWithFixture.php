<?php

namespace Tests\Unit\Support\Fixtures;

class ContainerMakeWithFixture
{
    public string $value;

    public function __construct(string $value = '')
    {
        $this->value = $value;
    }
}
