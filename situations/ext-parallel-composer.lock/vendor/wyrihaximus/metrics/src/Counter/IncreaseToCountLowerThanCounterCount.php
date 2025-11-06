<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Counter;

use InvalidArgumentException;

final class IncreaseToCountLowerThanCounterCount extends InvalidArgumentException
{
    public const string MESSAGE = 'Increase to count higher than counter count';

    //phpcs:disable
    public readonly int $increaseToCount;
    public readonly int $count;
    //phpcs:enable

    public static function create(int $increaseToCount, int $count): self
    {
        return new self(self::MESSAGE, $increaseToCount, $count);
    }

    private function __construct(string $message, int $increaseToCount, int $count)
    {
        parent::__construct($message);

        $this->increaseToCount = $increaseToCount;
        $this->count           = $count;
    }
}
