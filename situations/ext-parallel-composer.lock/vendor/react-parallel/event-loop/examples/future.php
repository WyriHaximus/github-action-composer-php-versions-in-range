<?php declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use function parallel\run;
use function React\Async\async;

require_once __DIR__ . '/../vendor/autoload.php';

$eventLoopBridge = new EventLoopBridge();

Loop::futureTick(async(static function () use ($eventLoopBridge) {
    $future = run(function (): string {
        return 'Hello World!';
    });

    echo $eventLoopBridge->await($future), PHP_EOL;
}));
