<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Pool\Infinite\Infinite;
use function React\Async\async;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$infinite = new Infinite(new EventLoopBridge(), 1);

Loop::futureTick(async(static function () use ($infinite) {
    echo $infinite->run(function () {
        sleep(1);

        return 'Hoi!';
    }), PHP_EOL;
    $infinite->close();
}));
