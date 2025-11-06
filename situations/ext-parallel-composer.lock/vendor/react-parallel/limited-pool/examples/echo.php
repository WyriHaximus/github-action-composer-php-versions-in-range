<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Pool\Infinite\Infinite;
use ReactParallel\Pool\Limited\Limited;
use function React\Async\async;

require __DIR__ . '/../vendor/autoload.php';

$limited = new Limited(
    new Infinite(new EventLoopBridge(), 1), // Another pool, preferably an inifinite pool
    100 // The amount of threads to start and keep running
);
$time = time();

Loop::futureTick(async(static function () use ($limited, $time) {
    echo 'Unix timestamp: ', $limited->run(function (int $time): int {
        return $time;
    }, [$time]), $time, PHP_EOL;
}));
