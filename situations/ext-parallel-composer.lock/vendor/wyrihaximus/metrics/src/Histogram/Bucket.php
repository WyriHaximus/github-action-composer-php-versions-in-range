<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Histogram;

final class Bucket
{
    private int $count = 0;

    public function __construct(private string $le)
    {
    }

    public static function createWithCount(string $le, int $count): self
    {
        $self        = new self($le);
        $self->count = $count;

        return $self;
    }

    public function incr(): void
    {
        $this->count++;
    }

    public function le(): string
    {
        return $this->le;
    }

    public function count(): int
    {
        return $this->count;
    }
}
