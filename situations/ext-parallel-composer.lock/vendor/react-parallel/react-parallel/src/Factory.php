<?php

declare(strict_types=1);

namespace ReactParallel;

use Closure;
use ReactParallel\Contracts\LowLevelPoolInterface;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Pool\Infinite\Infinite;
use ReactParallel\Pool\Limited\Limited;
use ReactParallel\Streams\Factory as StreamsFactory;

final class Factory
{
    private const float LOW_LEVEL_POOL_TTL = 0.666;

    private Metrics|null $metrics                    = null;
    private EventLoopBridge|null $eventLoopBridge    = null;
    private LowLevelPoolInterface|null $infinitePool = null;
    private StreamsFactory|null $streamsFactory      = null;

    public function withMetrics(Metrics $metrics): self
    {
        $self          = clone $this;
        $self->metrics = $metrics;

        return $self;
    }

    public function eventLoopBridge(): EventLoopBridge
    {
        if (! $this->eventLoopBridge instanceof EventLoopBridge) {
            $this->eventLoopBridge = new EventLoopBridge();
            if ($this->metrics instanceof Metrics) {
                $this->eventLoopBridge = $this->eventLoopBridge->withMetrics($this->metrics->eventLoop);
            }
        }

        return $this->eventLoopBridge;
    }

    public function streams(): StreamsFactory
    {
        if (! $this->streamsFactory instanceof StreamsFactory) {
            $this->streamsFactory = new StreamsFactory($this->eventLoopBridge());
        }

        return $this->streamsFactory;
    }

    /**
     * @param (Closure():T)|(Closure(A0):T)|(Closure(A0,A1):T)|(Closure(A0,A1,A2):T)|(Closure(A0,A1,A2,A3):T)|(Closure(A0,A1,A2,A3,A4):T)|(Closure():void)|(Closure(A0):void)|(Closure(A0,A1):void)|(Closure(A0,A1,A2):void)|(Closure(A0,A1,A2,A3):void)|(Closure(A0,A1,A2,A3,A4):void) $callable
     * @param array{}|array{A0}|array{A0,A1}|array{A0,A1,A2}|array{A0,A1,A2,A3}|array{A0,A1,A2,A3,A4}                                                                                                                                                                                   $args
     *
     * @return (
     *      $callable is (Closure():T) ? T : (
     *          $callable is (Closure(A0):T) ? T : (
     *              $callable is (Closure(A0,A1):T) ? T : (
     *                  $callable is (Closure(A0,A1,A2):T) ? T : (
     *                      $callable is (Closure(A0,A1,A2,A3):T) ? T : (
     *                          $callable is (Closure(A0,A1,A2,A3,A4):T) ? T : null
     *                      )
     *                  )
     *              )
     *          )
     *      )
     * )
     *
     * @template T
     * @template A0 (any number of function arguments, see https://github.com/phpstan/phpstan/issues/8214)
     * @template A1
     * @template A2
     * @template A3
     * @template A4
     */
    public function call(Closure $callable, array $args = []): mixed
    {
        return $this->lowLevelPool()->run($callable, $args);
    }

    public function lowLevelPool(): LowLevelPoolInterface
    {
        if (! $this->infinitePool instanceof LowLevelPoolInterface) {
            $this->infinitePool = new Infinite($this->eventLoopBridge(), self::LOW_LEVEL_POOL_TTL);
            if ($this->metrics instanceof Metrics) {
                $this->infinitePool = $this->infinitePool->withMetrics($this->metrics->infinitePool);
            }
        }

        return $this->infinitePool;
    }

    public function limitedPool(int $threadCount): Limited
    {
        return new Limited($this->lowLevelPool(), $threadCount);
    }
}
