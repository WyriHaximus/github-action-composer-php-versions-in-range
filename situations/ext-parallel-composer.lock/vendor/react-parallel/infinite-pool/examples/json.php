<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;

use ReactParallel\Pool\Infinite\Infinite;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

$json = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'large.json');

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$infinite = new Infinite(new EventLoopBridge(), 1);

Loop::futureTick(async(static function () use ($infinite, $json) {
    $promises = [];
    $signalHandler = static function () use ($infinite): void {
        Loop::stop();
        $infinite->close();
    };

    $tick = async(static function () use (&$promises, $infinite, $signalHandler, $json, &$tick): void {
        if (count($promises) < 1000) {
            $promises[] = async(static fn(string $json): string => $infinite->run(static function ($json): string {
                $json = json_decode($json, true);

                return md5(json_encode($json));
            }, [$json]))($json);
            Loop::futureTick($tick);

            return;
        }

        try {
            var_export(await(all($promises)));
        } finally {
            $infinite->close();
            Loop::removeSignal(SIGINT, $signalHandler);
            Loop::stop();
        }
    });

    Loop::futureTick($tick);
    Loop::addSignal(SIGINT, $signalHandler);
}));

echo 'Loop::run()', PHP_EOL;
