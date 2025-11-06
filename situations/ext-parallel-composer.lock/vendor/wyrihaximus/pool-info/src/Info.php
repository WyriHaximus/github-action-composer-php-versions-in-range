<?php

declare(strict_types=1);

namespace WyriHaximus\PoolInfo;

final class Info
{
    /**
     * The amount of workers busy handling calls.
     */
    public const BUSY = 'busy';

    /**
     * The amount of calls queued.
     */
    public const CALLS = 'calls';

    /**
     * The amount of workers idling waiting for calls.
     */
    public const IDLE = 'idle';

    /**
     * The current pool size.
     */
    public const SIZE = 'size';

    /**
     * The configured total pool size.
     *
     * This differs from SIZE as size might be lower for pools only starting workers
     * when there are calls to be handled.
     */
    public const TOTAL = 'total';
}
