<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Pool\Infinite\Infinite;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$infinite = new Infinite(new EventLoopBridge(), 1);

Loop::futureTick(async(static function () use ($infinite) {
    try {
        $infinite->run(static function (): void {
            throw new RuntimeException('Whoops I did it again!');
        });
    } finally {
        $infinite->close();
    }
}));
