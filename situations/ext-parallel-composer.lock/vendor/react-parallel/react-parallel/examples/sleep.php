<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\Factory as ParallelFactory;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$parallelFactory = new ParallelFactory();
$pool = $parallelFactory->limitedPool(100);

$timer = Loop::addPeriodicTimer(1, function () use ($pool) {
    var_export([...$pool->info()]);
});

$promises = [];
foreach (range(0, 250) as $i) {
    $promises[] = async(static fn(): int => $pool->run(static function($sleep): int {
        sleep($sleep);
        return $sleep;
    }, [random_int(1, 13)]))()->then(function (int $sleep) use ($i): int {
        echo $i, '; ', $sleep, PHP_EOL;

        return $sleep;
    });
}

$signalHandler = function () use ($pool) {
    Loop::stop();
    $pool->close();
};

Loop::futureTick(async(static function () use ($promises, $signalHandler, $pool, $timer) {
    await(all($promises));
    $pool->close();
    Loop::removeSignal(SIGINT, $signalHandler);
    Loop::cancelTimer($timer);
}));

Loop::addSignal(SIGINT, $signalHandler);

echo 'Loop::run()', PHP_EOL;
