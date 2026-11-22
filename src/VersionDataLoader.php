<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;

use function array_diff;
use function array_values;
use function count;
use function end;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function usort;

use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;

/**
 * Loads version data from versions.json and outputs highest/highestUpcoming/nightly versions.
 */
final readonly class VersionDataLoader
{
    public function __construct(
        private string $versionsPath,
    ) {
    }

    /**
     * Get the highest released version.
     */
    public function getHighest(): string
    {
        $data = $this->loadVersionData();
        usort($data['released'], versionCompareSort(...));

        /** @var string $highest */
        $highest = end($data['released']);

        return $highest;
    }

    /**
     * Get the highest upcoming version (excluding released).
     */
    public function getHighestUpcoming(): string
    {
        $data       = $this->loadVersionData();
        $unreleased = array_values(array_diff($data['upcoming'], $data['released']));
        usort($unreleased, versionCompareSort(...));

        if (count($unreleased) > 0) {
            /** @var string $highest */
            $highest = end($unreleased);

            return $highest;
        }

        usort($data['released'], versionCompareSort(...));

        /** @var string $highest */
        $highest = end($data['released']);

        return $highest;
    }

    /**
     * Get the highest nightly version (excluding released).
     */
    public function getNightly(): string
    {
        $data       = $this->loadVersionData();
        $unreleased = array_values(array_diff($data['nightly'], $data['released']));
        usort($unreleased, versionCompareSort(...));

        if (count($unreleased) > 0) {
            /** @var string $highest */
            $highest = end($unreleased);

            return $highest;
        }

        usort($data['released'], versionCompareSort(...));

        /** @var string $highest */
        $highest = end($data['released']);

        return $highest;
    }

    /**
     * Output version data to a file or stdout as GitHub Actions-compatible format.
     */
    public function output(string $outputPath = 'php://stdout'): void
    {
        $target = $outputPath;

        file_put_contents($target, 'highestUpcoming=' . $this->getHighestUpcoming() . "\n", FILE_APPEND);
        file_put_contents($target, 'nightly=' . $this->getNightly() . "\n", FILE_APPEND);
        file_put_contents($target, 'highest=' . $this->getHighest() . "\n", FILE_APPEND);
    }

    /** @return array<string, list<string>> */
    private function loadVersionData(): array
    {
        $content = file_get_contents($this->versionsPath);
        if ($content === false) {
            throw new RuntimeException('Cannot read versions.json');
        }

        /** @var array<string, list<string>> $data */
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return $data;
    }
}
