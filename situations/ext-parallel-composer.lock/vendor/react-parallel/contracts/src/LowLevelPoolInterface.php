<?php

declare(strict_types=1);

namespace ReactParallel\Contracts;

interface LowLevelPoolInterface extends PoolInterface
{
    public function acquireGroup(): GroupInterface;

    public function releaseGroup(GroupInterface $group): void;
}
