<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

interface Gauge
{
    public function gauge(): int;

    /** @return array<Label> */
    public function labels(): array;

    public function incr(): void;

    public function incrBy(int $incr): void;

    public function set(int $count): void;

    public function dcrBy(int $dcr): void;

    public function dcr(): void;
}
