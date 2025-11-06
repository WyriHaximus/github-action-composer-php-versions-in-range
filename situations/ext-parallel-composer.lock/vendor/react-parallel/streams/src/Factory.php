<?php

declare(strict_types=1);

namespace ReactParallel\Streams;

use parallel\Channel;
use ReactParallel\EventLoop\EventLoopBridge;

final class Factory
{
    public function __construct(private EventLoopBridge $loop)
    {
    }

    /**
     * @param Channel<T> $channel
     *
     * @return iterable<T>
     *
     * @template T
     */
    public function channel(Channel $channel): iterable
    {
        return $this->loop->observe($channel);
    }

    /**
     * @param Channel<T> $channel
     *
     * @return ?T
     *
     * @template T
     */
    public function single(Channel $channel): mixed
    {
        $stream = $this->channel($channel);
        foreach ($stream as $value) {
            return $value;
        }

        return null;
    }
}
