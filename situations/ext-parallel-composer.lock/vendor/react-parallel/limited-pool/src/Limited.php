<?php

declare(strict_types=1);

namespace ReactParallel\Pool\Limited;

use Closure;
use React\Promise\Deferred;
use ReactParallel\Contracts\ClosedException;
use ReactParallel\Contracts\GroupInterface;
use ReactParallel\Contracts\LowLevelPoolInterface;
use ReactParallel\Contracts\PoolInterface;
use SplQueue;
use WyriHaximus\PoolInfo\Info;

use function count;
use function React\Async\await;

final class Limited implements PoolInterface
{
    private int $idleRuntimes;

    /** @var SplQueue<callable> */
    private readonly SplQueue $queue;

    /** @phpstan-ignore shipmonk.uselessPrivatePropertyDefaultValue */
    private GroupInterface|null $group = null;

    private bool $closed = false;

    public function __construct(private readonly PoolInterface $pool, private readonly int $threadCount)
    {
        $this->idleRuntimes = $threadCount;
        $this->queue        = new SplQueue();

        if (! ($this->pool instanceof LowLevelPoolInterface)) {
            /** @phpstan-ignore shipmonk.returnInConstructor */
            return;
        }

        $this->group = $this->pool->acquireGroup();
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
    public function run(Closure $callable, array $args = []): mixed
    {
        if ($this->closed) {
            throw ClosedException::create();
        }

        if ($this->idleRuntimes === 0) {
            $deferred = new Deferred();
            $this->queue->enqueue(static fn () => $deferred->resolve(null));

            await($deferred->promise());
        }

        try {
            $this->idleRuntimes--;

            return $this->pool->run($callable, $args);
        } finally {
            $this->idleRuntimes++;
            $this->progressQueue();
        }
    }

    public function close(): bool
    {
        $this->closed = true;

        if ($this->pool instanceof LowLevelPoolInterface && $this->group instanceof GroupInterface) {
            $this->pool->releaseGroup($this->group);
        }

         $this->pool->close();

        return true;
    }

    public function kill(): bool
    {
        $this->closed = true;

        if ($this->pool instanceof LowLevelPoolInterface && $this->group instanceof GroupInterface) {
            $this->pool->releaseGroup($this->group);
        }

        $this->pool->kill();

        return true;
    }

    /** @return iterable<string, int> */
    public function info(): iterable
    {
        yield Info::TOTAL => $this->threadCount;
        yield Info::BUSY => $this->threadCount - $this->idleRuntimes;
        yield Info::CALLS => $this->queue->count();
        yield Info::IDLE  => $this->idleRuntimes;
        yield Info::SIZE  => $this->threadCount;
    }

    private function progressQueue(): void
    {
        if (count($this->queue) === 0) {
            return;
        }

        ($this->queue->dequeue())();
    }
}
