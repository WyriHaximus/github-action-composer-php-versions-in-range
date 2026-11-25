<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;
use Throwable;

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
use function trim;
use function usort;
use function version_compare;

final readonly class VersionDataUpdater
{
    public function __construct(
        private VersionCheckerInterface $versionChecker,
        private int $now,
    ) {
    }

    public function update(VersionData $data, string $tags, string $header): VersionData
    {
        try {
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
                if (! VersionHelpers::allowed($version)) {
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

            if ($released === []) {
                throw new RuntimeException('PHP upstream tags contain no stable releases');
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
            if (! VersionHelpers::allowed($nightly)) {
                throw new RuntimeException('Invalid PHP master version');
            }

            $releasedVersions = array_keys($released);
            usort($releasedVersions, VersionHelpers::compareSort(...));
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

            // Check for nightly when the version is not tracked and is newer than the highest released version.
            $shouldCheckNightly =
                ! in_array($nightly, $newNightly, true)
                && ! in_array($nightly, $newUpcoming, true)
                && version_compare($nightly, $highest, '>') === true;

            $observed   = $data->imageFirstSeen;
            $candidates = array_unique(array_merge(array_keys($prereleases), $newNightly));

            usort($candidates, VersionHelpers::compareSort(...));

            // Build a list of candidates that need image checks
            // Skip versions already promoted to upcoming
            $imageCheckCandidates = [];
            foreach ($candidates as $version) {
                if (
                    version_compare($version, $highest, '<=') !== false
                    || in_array($version, $newUpcoming, true)
                ) {
                    unset($observed[$version]);
                    continue;
                }

                $imageCheckCandidates[] = $version;
            }

            // Check nightly if needed
            if ($shouldCheckNightly && $this->versionChecker->nightlyExists($nightly)) {
                $newNightly[] = $nightly;
            }

            // Check all images in parallel
            $imageResults = CheckAllImages::checkAll($this->versionChecker, $imageCheckCandidates);

            // Process each image check result
            foreach ($imageCheckCandidates as $version) {
                if (! ($imageResults[$version] ?? false)) {
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

            // Clean up observed entries not in candidates
            foreach (array_keys($observed) as $version) {
                if (in_array($version, $candidates, true)) {
                    continue;
                }

                unset($observed[$version]);
            }

            return new VersionData($newReleased, $newUpcoming, $newNightly, $observed);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
