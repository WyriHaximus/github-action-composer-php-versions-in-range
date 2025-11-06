<?php

declare(strict_types=1);

namespace ReactParallel\EventLoop;

use parallel\Channel;
use parallel\Events;
use parallel\Events\Event;
use parallel\Future;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use Throwable;
use WyriHaximus\Metrics\Label;

use function count;
use function React\Async\await;
use function spl_object_hash;
use function spl_object_id;

final class EventLoopBridge
{
    private const array DEFAULT_SCALE_RANGE = [
        0.01,
        0.0075,
        0.0050,
        0.0025,
        0.001,
    ];

    private const int DEFAULT_SCALE_POSITION = self::START_COUNT;
    private const int START_COUNT            = 0;
    private const int EVENTS_0_TIMEOUT       = 0;
    private const int ONE_SCALE_POSITION     = 1;
    private const int SCALE_NO_ITEMS_CEILING = 10;

    private Metrics|null $metrics = null;

    /** @var Events<mixed> */
    private Events $events;

    private TimerInterface|null $timer = null;

    /** @var array<int, StreamInterface<mixed>> */
    private array $channels = [];

    /** @var array<int, Deferred<mixed>> */
    private array $futures = [];

    /** @var array<float> */
    private array $scaleRange      = self::DEFAULT_SCALE_RANGE;
    private int $scalePosition     = self::DEFAULT_SCALE_POSITION;
    private int $scaleNoItemsCount = self::START_COUNT;

    public function __construct()
    {
        $this->events = new Events();
        $this->events->setTimeout(self::EVENTS_0_TIMEOUT);
    }

    public function withMetrics(Metrics $metrics): self
    {
        $self          = clone $this;
        $self->metrics = $metrics;

        return $self;
    }

    /**
     * @param Channel<T> $channel
     *
     * @return iterable<T>
     *
     * @template T
     */
    public function observe(Channel $channel): iterable
    {
        /** @var Stream<T> $stream */
        $stream                                  = new Stream();
        $this->channels[spl_object_id($channel)] = $stream;
        $this->events->addChannel($channel);

        if ($this->metrics instanceof Metrics) {
            $this->metrics->channels()->gauge(new Label('state', 'active'))->incr();
        }

        $this->startTimer();

        yield from $stream->iterable();
    }

    /**
     * @param Future<T> $future
     *
     * @return T
     *
     * @template T
     */
    public function await(Future $future): mixed
    {
        /** @var Deferred<T> $deferred */
        $deferred                              = new Deferred();
        $this->futures[spl_object_id($future)] = $deferred;
        $this->events->addFuture(spl_object_hash($future), $future);

        if ($this->metrics instanceof Metrics) {
            $this->metrics->futures()->gauge(new Label('state', 'active'))->incr();
        }

        $this->startTimer();

        return await($deferred->promise());
    }

    private function startTimer(): void
    {
        if ($this->timer instanceof TimerInterface) {
            return;
        }

        if ($this->metrics instanceof Metrics) {
            $this->metrics->timer()->counter(new Label('event', 'start'))->incr();
        }

        $this->runTimer();
    }

    private function stopTimer(): void
    {
        if (! $this->timer instanceof TimerInterface || count($this->channels) !== self::START_COUNT || count($this->futures) !== self::START_COUNT) {
            return;
        }

        Loop::cancelTimer($this->timer);
        $this->timer = null;

        if (! ($this->metrics instanceof Metrics)) {
            return;
        }

        $this->metrics->timer()->counter(new Label('event', 'stop'))->incr();
    }

    private function runTimer(): void
    {
        $this->timer = Loop::addPeriodicTimer($this->scaleRange[$this->scalePosition], function (): void {
            $items = self::START_COUNT;

            try {
                $event = $this->events->poll();
                while ($event instanceof Event) {
                    $items++;

                    switch ($event->type) {
                        case Events\Event\Type::Read:
                            $this->handleReadEvent($event);
                            break;
                        case Events\Event\Type::Close:
                            $this->handleCloseEvent($event);
                            break;
                        case Events\Event\Type::Cancel:
                            $this->handleCancelEvent($event);
                            break;
                        case Events\Event\Type::Kill:
                            $this->handleKillEvent($event);
                            break;
                        case Events\Event\Type::Error:
                            $this->handleErrorEvent($event);
                            break;
                    }

                    $event = $this->events->poll();
                }
            } catch (Events\Error\Timeout) {
                // Catch and ignore this exception as it will trigger when events::poll() will have nothing for us
                // @ignoreException
            }

            $this->stopTimer();

            if ($items > self::START_COUNT && isset($this->scaleRange[$this->scalePosition + self::ONE_SCALE_POSITION])) {
                if ($this->timer instanceof TimerInterface) {
                    Loop::cancelTimer($this->timer);
                    $this->timer = null;
                }

                $this->scalePosition++;
                $this->runTimer();

                $this->scaleNoItemsCount = self::START_COUNT;
            }

            if ($items === self::START_COUNT) {
                $this->scaleNoItemsCount++;

                if ($this->scaleNoItemsCount > self::SCALE_NO_ITEMS_CEILING && isset($this->scaleRange[$this->scalePosition - self::ONE_SCALE_POSITION])) {
                    if ($this->timer instanceof TimerInterface) {
                        Loop::cancelTimer($this->timer);
                        $this->timer = null;
                    }

                    $this->scalePosition--;
                    $this->runTimer();

                    $this->scaleNoItemsCount = self::START_COUNT;
                }
            }

            if (! ($this->metrics instanceof Metrics)) {
                return;
            }

            $this->metrics->timer()->counter(new Label('event', 'tick'))->incr();
            $this->metrics->timerItems()->counter(new Label('count', (string) $items))->incr();
        });
    }

    /** @param Event<mixed> $event */
    private function handleReadEvent(Event $event): void
    {
        if ($event->object instanceof Future) {
            $this->handleFutureReadEvent($event);
        }

        if (! ($event->object instanceof Channel)) {
            return;
        }

        $this->handleChannelReadEvent($event);
    }

    /** @param Event<mixed> $event */
    private function handleFutureReadEvent(Event $event): void
    {
        $this->futures[spl_object_id($event->object)]->resolve($event->value);
        unset($this->futures[spl_object_id($event->object)]);

        if (! ($this->metrics instanceof Metrics)) {
            return;
        }

        $futures = $this->metrics->futures();
        $futures->gauge(new Label('state', 'active'))->dcr();
        $futures->gauge(new Label('state', 'resolve'))->incr();
    }

    /** @param Event<mixed> $event */
    private function handleChannelReadEvent(Event $event): void
    {
        $this->channels[spl_object_id($event->object)]->value($event->value);
        $this->events->addChannel($event->object); /** @phpstan-ignore-line */

        if (! ($this->metrics instanceof Metrics)) {
            return;
        }

        $this->metrics->channelMessages()->counter(new Label('event', 'read'))->incr();
    }

    /** @param Event<mixed> $event */
    private function handleCloseEvent(Event $event): void
    {
        $this->channels[spl_object_id($event->object)]->done();
        unset($this->channels[spl_object_id($event->object)]);

        if (! ($this->metrics instanceof Metrics)) {
            return;
        }

        $channels = $this->metrics->channels();
        $channels->gauge(new Label('state', 'active'))->dcr();
        $channels->gauge(new Label('state', 'close'))->incr();
    }

    /** @param Event<mixed> $event */
    private function handleCancelEvent(Event $event): void
    {
        $this->futures[spl_object_id($event->object)]->reject(new CanceledFuture());
        unset($this->futures[spl_object_id($event->object)]);

        if (! ($this->metrics instanceof Metrics)) {
            return;
        }

        $futures = $this->metrics->futures();
        $futures->gauge(new Label('state', 'active'))->dcr();
        $futures->gauge(new Label('state', 'cancel'))->incr();
    }

    /** @param Event<mixed> $event */
    private function handleKillEvent(Event $event): void
    {
        $this->futures[spl_object_id($event->object)]->reject(new KilledRuntime());
        unset($this->futures[spl_object_id($event->object)]);

        if (! ($this->metrics instanceof Metrics)) {
            return;
        }

        $futures = $this->metrics->futures();
        $futures->gauge(new Label('state', 'active'))->dcr();
        $futures->gauge(new Label('state', 'kill'))->incr();
    }

    /** @param Event<Throwable> $event */
    private function handleErrorEvent(Event $event): void
    {
        if (! ($event->object instanceof Future)) {
            return;
        }

        $this->futures[spl_object_id($event->object)]->reject($event->value);
        unset($this->futures[spl_object_id($event->object)]);

        if (! ($this->metrics instanceof Metrics)) {
            return;
        }

        $futures = $this->metrics->futures();
        $futures->gauge(new Label('state', 'active'))->dcr();
        $futures->gauge(new Label('state', 'error'))->incr();
    }
}
