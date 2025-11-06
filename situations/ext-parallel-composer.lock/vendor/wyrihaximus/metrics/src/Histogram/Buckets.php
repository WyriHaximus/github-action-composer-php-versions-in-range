<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Histogram;

final class Buckets
{
    /** @var array<float> */
    private array $buckets;

    public function __construct(float ...$buckets)
    {
        $this->buckets = $buckets;
    }

    /** @return array<float> */
    public function buckets(): array
    {
        return $this->buckets;
    }
}
