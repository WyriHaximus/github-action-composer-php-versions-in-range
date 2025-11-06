<?php

declare(strict_types=1);

namespace WyriHaximus\PoolInfo;

trait PoolInfoTrait
{
    /** @return iterable<string, int> */
    final public function info(): iterable
    {
        yield Info::TOTAL => $this->infoTotal();
        yield Info::BUSY => $this->infoBusy();
        yield Info::CALLS => $this->infoCalls();
        yield Info::IDLE  => $this->infoIdle();
        yield Info::SIZE  => $this->infoSize();
    }

    abstract protected function infoBusy(): int;

    abstract protected function infoCalls(): int;

    abstract protected function infoIdle(): int;

    abstract protected function infoSize(): int;

    abstract protected function infoTotal(): int;
}
