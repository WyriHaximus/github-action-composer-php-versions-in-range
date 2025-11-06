<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use ReactParallel\Pool\Infinite\Infinite;
use ReactParallel\Pool\Limited\Limited;
use function React\Async\async;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$limited = new Limited(new Infinite(new EventLoopBridge(), 1), 2);

Loop::futureTick(async(static function () use ($limited) {
    Loop::addTimer(1, static function () use ($limited): void {
        $limited->kill();
        Loop::stop();
    });

    var_export(
        $limited->run(
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
