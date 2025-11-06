<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

use WyriHaximus\Metrics\Summary\Quantile;

interface Summary
{
    /** @return iterable<Quantile> */
    public function quantiles(): iterable;

    /** @return array<Label> */
    public function labels(): array;

    public function observe(float $value): void;
}
