<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;

use function exec;
use function implode;

/**
 * Fetches PHP source tags from git.
 */
final class GitTagFetcher implements GitTagFetcherInterface
{
    public function fetch(): string
    {
        exec(
            'git ls-remote --tags --refs https://github.com/php/php-src.git 2>&1',
            $lines,
            $exit,
        );
        if ($exit !== 0 || $lines === []) {
            throw new RuntimeException('Unable to fetch PHP tags: ' . implode("\n", $lines));
        }

        return implode("\n", $lines);
    }
}
