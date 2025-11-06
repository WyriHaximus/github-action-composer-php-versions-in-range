<?php

declare(strict_types=1);

namespace ReactParallel\EventLoop;

/** @template T */
interface StreamInterface
{
    /** @param T $value */
    public function value(mixed $value): void;

    public function done(): void;

    /** @return iterable<T> */
    public function iterable(): iterable;
}
