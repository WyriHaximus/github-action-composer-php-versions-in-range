<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use React\EventLoop\Loop;
use ReactParallel\Factory as ParallelFactory;
use function React\Async\async;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$parallelFactory = new ParallelFactory();
$pool = $parallelFactory->limitedPool(2);

Loop::futureTick(async(static function () use ($pool, $timer) {
    var_export(
        $pool->run(
            static fn (): array => array_merge(
                ...array_map(
                    static fn (string $package): array => [
                        $package => InstalledVersions::getPrettyVersion($package),
                    ],
                    InstalledVersions::getInstalledPackages(),
                )
            )
        )
    );

    $pool->close();
    Loop::cancelTimer($timer);
}));
