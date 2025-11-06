<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Pool\Infinite\Infinite;
use function React\Async\async;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$infinite = new Infinite(new EventLoopBridge(), 0.1);

Loop::futureTick(async(static function () use ($infinite) {
    Loop::addTimer(1, static function () use ($infinite): void {
        $infinite->kill();
        Loop::stop();
    });

    var_export(
        $infinite->run(
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
}));
