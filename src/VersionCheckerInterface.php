<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use React\Promise\PromiseInterface;

interface VersionCheckerInterface
{
    /** @return PromiseInterface<bool> */
    public function imageExists(string $version): PromiseInterface;

    public function nightlyExists(string $version): bool;
}
