<?php

declare(strict_types=1);

namespace ReactParallel\EventLoop;

use React\Promise\Deferred;
use SplQueue;

use function React\Async\await;

/**
 * @template T
 * @template-implements StreamInterface<T>
 */
final class Stream implements StreamInterface
{
    /** @var SplQueue<T> */
    private readonly SplQueue $queue;

    /** @var Deferred<Value|Done> */
    private Deferred $wait;

    public function __construct()
    {
        $this->queue = new SplQueue();
        $this->queue->setIteratorMode(SplQueue::IT_MODE_DELETE | SplQueue::IT_MODE_DELETE);
        $this->wait = new Deferred();
    }

    /** @param T $value */
    public function value(mixed $value): void
    {
        $this->queue->enqueue($value);
        $this->wait->resolve(new Value());
    }

    public function done(): void
    {
        $this->wait->resolve(new Done());
    }

    /** @return iterable<T> */
    public function iterable(): iterable
    {
        for (;;) {
            $type = await($this->wait->promise());

            foreach ($this->queue as $value) {
                yield $value;
            }

            if ($type instanceof Done) {
                break;
            }

            $this->wait = new Deferred();
        }
    }
}
