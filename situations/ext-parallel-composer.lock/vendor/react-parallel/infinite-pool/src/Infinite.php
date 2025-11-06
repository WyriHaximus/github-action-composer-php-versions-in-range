<?php

declare(strict_types=1);

namespace ReactParallel\Pool\Infinite;

use Closure;
use parallel\Runtime\Error\Closed;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use ReactParallel\Contracts\ClosedException;
use ReactParallel\Contracts\GroupInterface;
use ReactParallel\Contracts\LowLevelPoolInterface;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Runtime\Runtime;
use WyriHaximus\Metrics\Label;
use WyriHaximus\PoolInfo\Info;

use function array_key_exists;
use function array_keys;
use function array_pop;
use function count;
use function hrtime;
use function spl_object_id;

use const WyriHaximus\Constants\Boolean\FALSE_;
use const WyriHaximus\Constants\Boolean\TRUE_;

final class Infinite implements LowLevelPoolInterface
{
    /** @var Runtime[] */
    private array $runtimes = [];

    /** @var int[] */
    private array $idleRuntimes = [];

    /** @var TimerInterface[] */
    private array $ttlTimers = [];

    private Metrics|null $metrics = null;

    /** @var GroupInterface[] */
    private array $groups = [];

    private bool $closed = FALSE_;

    public function __construct(private EventLoopBridge $eventLoopBridge, private float $ttl)
    {
    }

    public function withMetrics(Metrics $metrics): self
    {
        $self          = clone $this;
        $self->metrics = $metrics;

        return $self;
    }

    /**
     * {@inheritDoc}
     */
    public function run(Closure $callable, array $args = []): mixed
    {
        if ($this->closed === TRUE_) {
            throw ClosedException::create();
        }

        $runtime = count($this->idleRuntimes) === 0 ? $this->spawnRuntime() : $this->getIdleRuntime();

        $time = null;
        if ($this->metrics instanceof Metrics) {
            $this->metrics->threads->gauge(new Label('state', 'busy'))->incr();
            $this->metrics->threads->gauge(new Label('state', 'idle'))->dcr();
            $time = hrtime(true);
        }

        try {
            return $runtime->run(
                $callable,
                $args,
            );
        } finally {
            if ($this->metrics instanceof Metrics) {
                $this->metrics->executionTime->summary()->observe((hrtime(true) - $time) / 1e+9);
                $this->metrics->threads->gauge(new Label('state', 'idle'))->incr();
                $this->metrics->threads->gauge(new Label('state', 'busy'))->dcr();
            }

            if ($this->ttl >= 0.1) {
                $this->addRuntimeToIdleList($runtime);
                $this->startTtlTimer($runtime);
            } else {
                $this->closeRuntime(spl_object_id($runtime));
            }
        }
    }

    public function close(): bool
    {
        if (count($this->groups) > 0) {
            return FALSE_;
        }

        $this->closed = TRUE_;

        foreach (array_keys($this->runtimes) as $id) {
            $this->closeRuntime($id);
        }

        return TRUE_;
    }

    public function kill(): bool
    {
        if (count($this->groups) > 0) {
            return FALSE_;
        }

        $this->closed = TRUE_;

        foreach ($this->runtimes as $runtime) {
            $runtime->kill();
        }

        return TRUE_;
    }

    /**
     * {@inheritDoc}
     */
    public function info(): iterable
    {
        yield Info::TOTAL => count($this->runtimes);
        yield Info::BUSY => count($this->runtimes) - count($this->idleRuntimes);
        yield Info::CALLS => 0;
        yield Info::IDLE  => count($this->idleRuntimes);
        yield Info::SIZE  => count($this->runtimes);
    }

    public function acquireGroup(): GroupInterface
    {
        $group                         = Group::create();
        $this->groups[(string) $group] = $group;

        return $group;
    }

    public function releaseGroup(GroupInterface $group): void
    {
        unset($this->groups[(string) $group]);
    }

    private function getIdleRuntime(): Runtime
    {
        $id = array_pop($this->idleRuntimes);

        if ($id !== null) {
            if (array_key_exists($id, $this->ttlTimers)) {
                Loop::cancelTimer($this->ttlTimers[$id]);
                unset($this->ttlTimers[$id]);
            }

            if (array_key_exists($id, $this->runtimes)) {
                return $this->runtimes[$id];
            }
        }

        return $this->spawnRuntime();
    }

    private function addRuntimeToIdleList(Runtime $runtime): void
    {
        $id                      = spl_object_id($runtime);
        $this->idleRuntimes[$id] = $id;
    }

    private function spawnRuntime(): Runtime
    {
        $runtime                                 = Runtime::create($this->eventLoopBridge);
        $this->runtimes[spl_object_id($runtime)] = $runtime;

        if ($this->metrics instanceof Metrics) {
            $this->metrics->threads->gauge(new Label('state', 'idle'))->incr();
        }

        return $runtime;
    }

    private function startTtlTimer(Runtime $runtime): void
    {
        $id = spl_object_id($runtime);

        $this->ttlTimers[$id] = Loop::addTimer($this->ttl, function () use ($id): void {
            $this->closeRuntime($id);
        });
    }

    private function closeRuntime(int $id): void
    {
        if (! array_key_exists($id, $this->runtimes)) {
            return;
        }

        // check if it exists
        $runtime = $this->runtimes[$id];
        try {
            $runtime->close();
        } catch (Closed) {
            // @ignoreException
        }

        unset($this->runtimes[$id]);

        if (array_key_exists($id, $this->idleRuntimes)) {
            unset($this->idleRuntimes[$id]);
        }

        if ($this->metrics instanceof Metrics) {
            $this->metrics->threads->gauge(new Label('state', 'idle'))->dcr();
        }

        if (! array_key_exists($id, $this->ttlTimers)) {
            return;
        }

        Loop::cancelTimer($this->ttlTimers[$id]);

        unset($this->ttlTimers[$id]);
    }
}
