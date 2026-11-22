<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function end;
use function in_array;
use function preg_match;
use function preg_split;
use function str_starts_with;
use function trim;
use function usort;
use function version_compare;

/**
 * Updates VersionData based on PHP source tags, version headers, and registry checks.
 */
final class VersionDataUpdater
{
    /** @var callable(string): bool */
    private $imageExists;
    /** @var callable(string): bool */
    private $nightlyExists;

    public function __construct(
        callable $imageExists,
        callable $nightlyExists,
        private readonly int $now,
    ) {
        $this->imageExists   = $imageExists;
        $this->nightlyExists = $nightlyExists;
    }

    public function update(VersionData $data, string $tags, string $header): VersionData
    {
        $released    = [];
        $prereleases = [];

        /** @var list<string> $lines */
        $lines = preg_split('/\R/', trim($tags));
        foreach ($lines as $line) {
            $matchResult = preg_match(
                '/^[a-f0-9]{40,64}\s+refs\/tags\/php-(\d+)\.(\d+)\.(\d+)(?:(alpha|beta|RC)\d+)?$/iD',
                $line,
                $match,
            );
            if ($matchResult !== 1) {
                continue;
            }

            $version = $match[1] . '.' . $match[2];
            if (! versionAllowed($version)) {
                continue;
            }

            $hasPrerelease = array_key_exists(4, $match);
            if ($hasPrerelease) {
                if ($match[3] === '0') {
                    $prereleases[$version] = true;
                }
            } else {
                $released[$version] = true;
            }
        }

        if (
            ! array_key_exists('5.3', $released)
            || ! array_key_exists('7.0', $released)
            || ! array_key_exists('8.0', $released)
        ) {
            throw new RuntimeException('Incomplete PHP upstream tags');
        }

        $majorResult = preg_match('/^#define\s+PHP_MAJOR_VERSION\s+(\d+)\s*$/m', $header, $major);
        $minorResult = preg_match('/^#define\s+PHP_MINOR_VERSION\s+(\d+)\s*$/m', $header, $minor);
        $fullResult  = preg_match('/^#define\s+PHP_VERSION\s+"(\d+)\.(\d+)\.\d+-dev"\s*$/m', $header, $full);

        if (
            $majorResult !== 1 || $minorResult !== 1 || $fullResult !== 1
            || $major[1] !== $full[1]
            || $minor[1] !== $full[2]
        ) {
            throw new RuntimeException('Invalid PHP master version header');
        }

        $nightly = $major[1] . '.' . $minor[1];
        if (! versionAllowed($nightly)) {
            throw new RuntimeException('Invalid PHP master version');
        }

        $releasedVersions = array_keys($released);
        usort($releasedVersions, versionCompareSort(...));
        /** @var non-falsy-string $highest */
        $highest = end($releasedVersions);

        $newReleased = $data->released;
        foreach ($newReleased as $existingVersion) {
            if (version_compare($existingVersion, $highest, '>') !== true) {
                continue;
            }

            $highest = $existingVersion;
        }

        foreach ($releasedVersions as $version) {
            if (in_array($version, $newReleased, true)) {
                continue;
            }

            $newReleased[] = $version;
        }

        $newUpcoming = array_values(array_diff($data->upcoming, $newReleased));
        $newNightly  = array_values(array_diff($data->nightly, $newReleased, $newUpcoming));

        if (
            version_compare($nightly, $highest, '>') === true
            && ! in_array($nightly, $newUpcoming, true)
            && ! in_array($nightly, $newNightly, true)
            && ($this->nightlyExists)($nightly)
        ) {
            $newNightly[] = $nightly;
        }

        $observed   = $data->imageFirstSeen;
        $candidates = array_unique(array_merge(array_keys($prereleases), $newNightly));

        usort($candidates, versionCompareSort(...));

        foreach ($candidates as $version) {
            if (
                version_compare($version, $highest, '<=') !== false
                || in_array($version, $newUpcoming, true)
            ) {
                unset($observed[$version]);
                continue;
            }

            if (! ($this->imageExists)($version)) {
                unset($observed[$version]);
                continue;
            }

            $observed[$version] ??= $this->now;

            if ($this->now - $observed[$version] < 86400) {
                continue;
            }

            $newUpcoming[] = $version;
            $newNightly    = array_values(array_diff($newNightly, [$version]));
            unset($observed[$version]);
        }

        foreach (array_keys($observed) as $version) {
            if (in_array($version, $candidates, true)) {
                continue;
            }

            unset($observed[$version]);
        }

        if ($observed === []) {
            $observed = null;
        }

        return new VersionData($newReleased, $newUpcoming, $newNightly, $observed ?? []);
    }
}

/**
 * Check if a PHP version is allowed (>= 5.3, not 6.x).
 */
function versionAllowed(string $version): bool
{
    return version_compare($version, '5.3', '>=') !== false && ! str_starts_with($version, '6.');
}

/**
 * Sort callback for version strings.
 */
function versionCompareSort(string $a, string $b): int
{
    return version_compare($a, $b) !== 0 ? (version_compare($a, $b) > 0 ? 1 : -1) : 0;
}
