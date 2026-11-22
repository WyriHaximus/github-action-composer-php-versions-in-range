<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

/**
 * Fetches PHP source tags from git.
 */
interface GitTagFetcherInterface
{
    public function fetch(): string;
}
