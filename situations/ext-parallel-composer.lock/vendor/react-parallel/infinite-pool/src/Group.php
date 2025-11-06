<?php

declare(strict_types=1);

namespace ReactParallel\Pool\Infinite;

use ReactParallel\Contracts\GroupInterface;

use function bin2hex;
use function function_exists;
use function md5;
use function random_bytes;
use function spl_object_hash;

final readonly class Group implements GroupInterface
{
    private const int BYTES = 16;

    private function __construct(private string $id)
    {
    }

    public static function create(): self
    {
        if (function_exists('random_bytes')) {
            return new self(bin2hex(random_bytes(self::BYTES)));
        }

        return new self(md5(spl_object_hash(new self('a'))));
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
