<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Summary;

final class Quantiles
{
    /** @var array<float> */
    private array $quantiles;

    public function __construct(float ...$quantiles)
    {
        $this->quantiles = $quantiles;
    }

    /** @return array<float> */
    public function quantiles(): array
    {
        return $this->quantiles;
    }
}
