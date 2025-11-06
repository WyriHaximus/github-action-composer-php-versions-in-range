<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Label;

final class Name
{
    public function __construct(private string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }
}
