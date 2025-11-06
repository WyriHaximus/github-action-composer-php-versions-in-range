<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

final class Label
{
    public function __construct(private string $name, private string $value)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->value;
    }
}
