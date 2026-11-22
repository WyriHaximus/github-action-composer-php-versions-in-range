<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;

use function chmod;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function json_decode;
use function rename;
use function strlen;
use function tempnam;
use function time;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Runs the full version update pipeline: fetch tags, header, check registries, and persist.
 */
final readonly class VersionDataRunner
{
    public function __construct(
        private VersionChecker $versionChecker,
        private GitTagFetcherInterface $gitTagFetcher,
    ) {
    }

    public function run(string $path): void
    {
        if (! file_exists($path)) {
            throw new RuntimeException('Cannot read ' . $path);
        }

        $existing = file_get_contents($path);
        if ($existing === false) {
            throw new RuntimeException('Cannot read ' . $path);
        }

        $data = VersionData::fromJson($existing);

        $tags = $this->gitTagFetcher->fetch();

        $header = $this->versionChecker->getHttpClient()->getRequired('https://raw.githubusercontent.com/php/php-src/master/main/php_version.h');

        $updater = new VersionDataUpdater(
            $this->versionChecker->imageExists(...),
            $this->versionChecker->nightlyExists(...),
            time(),
        );

        $updated = $updater->update($data, $tags, $header);

        $json = $updated->toJson();

        if ($updated->jsonSerialize() === json_decode($existing, true, 512, JSON_THROW_ON_ERROR)) {
            return;
        }

        $temp = tempnam(dirname($path), '.versions-');
        if ($temp === false) {
            throw new RuntimeException('Cannot create temporary version file');
        }

        try {
            $written = file_put_contents($temp, $json);
            if ($written === false || $written !== strlen($json)) {
                throw new RuntimeException('Cannot write ' . $path);
            }

            $perms = fileperms($path);
            if ($perms === false || ! chmod($temp, $perms & 0777) || ! rename($temp, $path)) {
                throw new RuntimeException('Cannot write ' . $path);
            }
        } finally {
            if (file_exists($temp)) {
                unlink($temp);
            }
        }
    }
}
