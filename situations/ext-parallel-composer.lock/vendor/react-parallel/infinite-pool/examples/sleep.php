<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Pool\Infinite\Infinite;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function WyriHaximus\iteratorOrArrayToArray;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$infinite = new Infinite(new EventLoopBridge(), 0.1);

Loop::futureTick(async(static function () use ($infinite) {
    $timer = Loop::addPeriodicTimer(1, static function () use ($infinite): void {
        var_export(iteratorOrArrayToArray($infinite->info()));
    });

    $promises = [];
    foreach (range(0, 250) as $i) {
        $promises[] = async(static function (Infinite $infinite, int $i): int {
            $sleep = $infinite->run(static function (int $sleep): int {
                sleep($sleep);

                return $sleep;
            }, [random_int(1, 13)]);

            echo $i, '; ', $sleep, PHP_EOL;

            return $sleep;
        })($infinite, $i);
    }

    $signalHandler = static function () use ($infinite): void {
        $infinite->close();
        Loop::stop();
    };

    Loop::addSignal(SIGINT, $signalHandler);

    await(all($promises));

    $infinite->close();
    Loop::removeSignal(SIGINT, $signalHandler);
    Loop::cancelTimer($timer);
}));
