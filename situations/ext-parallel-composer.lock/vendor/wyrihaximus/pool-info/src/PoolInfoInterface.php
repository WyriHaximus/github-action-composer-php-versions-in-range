<?php

declare(strict_types=1);

namespace WyriHaximus\PoolInfo;

interface PoolInfoInterface
{
    /** @return iterable<string, int> */
    public function info(): iterable;
}
