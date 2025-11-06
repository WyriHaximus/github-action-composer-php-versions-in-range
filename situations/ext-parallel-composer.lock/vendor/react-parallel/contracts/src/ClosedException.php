<?php

declare(strict_types=1);

namespace ReactParallel\Contracts;

use Exception;

final class ClosedException extends Exception
{
    public static function create(): self
    {
        return new self('Pool is closed and won\'t run your Closure');
    }
}
