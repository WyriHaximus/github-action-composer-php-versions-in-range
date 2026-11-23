<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use function React\Async\await;
use function React\Promise\all;

final class CheckAllImages
{
    /**
     * Check if images exist for multiple versions in parallel.
     *
     * @param list<string> $versions
     *
     * @return array<string, bool>
     */
    public static function checkAll(VersionCheckerInterface $checker, array $versions): array
    {
        if ($versions === []) {
            return [];
        }

        $promises = [];
        foreach ($versions as $version) {
            $promises[$version] = $checker->imageExists($version);
        }

        $results = await(all($promises));

        /** @var array<string, bool> $imageExistsMap */
        $imageExistsMap = [];
        foreach ($versions as $version) {
            $imageExistsMap[$version] = $results[$version] ?? false;
        }

        return $imageExistsMap;
    }
}
