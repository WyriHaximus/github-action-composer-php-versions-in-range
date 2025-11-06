<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

interface Counter
{
    public function count(): int;

    /** @return array<Label> */
    public function labels(): array;

    public function incr(): void;

    public function incrBy(int $incr): void;

    public function incrTo(int $count): void;
}
