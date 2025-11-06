<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use ReactParallel\Factory as ParallelFactory;
use function React\Async\async;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$parallelFactory = new ParallelFactory();
$pool = $parallelFactory->limitedPool(1);

Loop::futureTick(async(static function () use ($pool): void {
    try {
        $pool->run(static function () {
            throw new RuntimeException('Whoops I did it again!');

            return 'We shouldn\'t reach this!';
        });
    } catch (Throwable $error) {
        echo $error, PHP_EOL;
    } finally {
        $pool->close();
    }
}));
