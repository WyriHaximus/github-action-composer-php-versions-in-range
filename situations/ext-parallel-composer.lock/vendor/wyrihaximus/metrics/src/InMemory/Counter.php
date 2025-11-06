<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\InMemory;

use WyriHaximus\Metrics\Counter as CounterInterface;
use WyriHaximus\Metrics\Counter\IncreaseToCountLowerThanCounterCount;
use WyriHaximus\Metrics\Label;

final class Counter implements CounterInterface
{
    private int $count = 0;
    /** @var array<Label> */
    private readonly array $labels;

    public function __construct(Label ...$labels)
    {
        $this->labels = $labels;
    }

    public function count(): int
    {
        return $this->count;
    }

    /** @return array<Label> */
    public function labels(): array
    {
        return $this->labels;
    }

    public function incr(): void
    {
        $this->count++;
    }

    public function incrBy(int $incr): void
    {
        $this->count += $incr;
    }

    public function incrTo(int $count): void
    {
        if ($count < $this->count) {
            throw IncreaseToCountLowerThanCounterCount::create($count, $this->count);
        }

        $this->count = $count;
    }
}
