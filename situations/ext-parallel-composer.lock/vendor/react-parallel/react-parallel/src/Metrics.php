<?php

declare(strict_types=1);

namespace ReactParallel;

use ReactParallel\EventLoop\Metrics as EventLoopMetrics;
use ReactParallel\Pool\Infinite\Metrics as InfinitePoolMetrics;
use WyriHaximus\Metrics\Registry;

final readonly class Metrics
{
    public function __construct(
        public EventLoopMetrics $eventLoop,
        public InfinitePoolMetrics $infinitePool,
    ) {
    }

    public static function create(Registry $registry): self
    {
        return new self(
            EventLoopMetrics::create($registry),
            InfinitePoolMetrics::create($registry),
        );
    }

    /** @deprecated Use eventLoop property instead */
    public function eventLoop(): EventLoopMetrics
    {
        return $this->eventLoop;
    }

    /** @deprecated Use infinitePool property instead */
    public function infinitePool(): InfinitePoolMetrics
    {
        return $this->infinitePool;
    }
}
