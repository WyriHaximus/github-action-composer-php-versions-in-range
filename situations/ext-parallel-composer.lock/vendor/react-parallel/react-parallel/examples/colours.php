<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\Factory as ParallelFactory;
use function React\Async\async;

$options = getopt(
    '',
    [
        'iterations:',
        'delay::',
    ],
);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

echo 'Loop: ', Loop::get()::class, PHP_EOL;

$parallelFactory = new ParallelFactory();
$pool = $parallelFactory->lowLevelPool();

foreach (range(0, 7) as $i) {
    Loop::futureTick(async(static function () use ($i, $pool, $options): void {
        $pool->run(static function (int $index, int $iterations, bool $delay): bool {
            for ($i = 0; $i < $iterations; $i++) {
                if ($delay) {
                    usleep((int) ($i * 0.3));
                }
                echo "\033[" . (30 + $index) . ";" . (40 + $index) . "m.\033[0m";
            }
            return true;
        }, [$i, (int)$options['iterations'], isset($options['delay'])]);
    }));
}

echo PHP_EOL, 'Loop::run()', PHP_EOL;
