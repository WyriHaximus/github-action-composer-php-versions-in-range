<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

use WyriHaximus\Metrics\Histogram\Bucket;

interface Histogram
{
    /** @return iterable<Bucket> */
    public function buckets(): iterable;

    public function summary(): float;

    public function count(): int;

    /** @return array<Label> */
    public function labels(): array;

    public function observe(float $value): void;
}
