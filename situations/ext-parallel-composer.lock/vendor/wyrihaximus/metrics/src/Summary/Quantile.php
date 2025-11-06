<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Summary;

final class Quantile
{
    public function __construct(private string $quantile, private float $value)
    {
    }

    public function quantile(): string
    {
        return $this->quantile;
    }

    public function value(): float
    {
        return $this->value;
    }
}
