<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use function str_starts_with;
use function version_compare;

final class VersionHelpers
{
    public static function allowed(string $version): bool
    {
        return version_compare($version, '5.3', '>=') !== false && ! str_starts_with($version, '6.');
    }

    public static function compareSort(string $a, string $b): int
    {
        return version_compare($a, $b) !== 0 ? (version_compare($a, $b) > 0 ? 1 : -1) : 0;
    }
}
