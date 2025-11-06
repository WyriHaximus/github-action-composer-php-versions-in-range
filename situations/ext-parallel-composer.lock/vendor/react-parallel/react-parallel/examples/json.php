<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\Factory as ParallelFactory;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

$json = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'large.json');

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$parallelFactory = new ParallelFactory();
$pool = $parallelFactory->limitedPool(150);

$signalHandler = function () use ($pool, &$signalHandler) {
    Loop::removeSignal(SIGINT, $signalHandler);
    $pool->close();
};
Loop::addSignal(SIGINT, $signalHandler);

$promises = [];
foreach (range(0, 5000) as $i) {
    $promises[] = async(static fn(): string => $pool->run(function($json) {
        $json = json_decode($json, true);
        return md5(json_encode($json));
    }, [$json]))();
}

Loop::futureTick(async(static function () use ($promises, $signalHandler): void {
    try {
        var_export(await(all($promises)));
    } finally {
        $signalHandler();
    }
}));

echo 'Loop::run()', PHP_EOL;
