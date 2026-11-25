<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;

use function array_diff;
use function array_values;
use function end;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function usort;
use function WyriHaximus\Twig\render;

use const JSON_THROW_ON_ERROR;

final readonly class Generator
{
    public function __construct(
        private string $projectRoot,
        private string $etcDir,
    ) {
    }

    public function generate(): void
    {
        $content = file_get_contents($this->etcDir . '/versions.json');
        if ($content === false) {
            throw new RuntimeException('Cannot read versions.json');
        }

        /** @var array<string, list<string>> $versions */
        $versions = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        foreach (['upcoming', 'nightly'] as $category) {
            $versions[$category] = array_values(array_diff($versions[$category], $versions['released']));
        }

        foreach (['released', 'upcoming', 'nightly'] as $category) {
            usort($versions[$category], VersionHelpers::compareSort(...));
        }

        $readmeTemplate = file_get_contents($this->etcDir . '/README.md.twig');
        if ($readmeTemplate === false) {
            throw new RuntimeException('Cannot read README.md.twig');
        }

        file_put_contents(
            $this->projectRoot . '/README.md',
            render(
                $readmeTemplate,
                [
                    'upcoming' => $versions['upcoming'] === [] ? null : end($versions['upcoming']),
                    'nightly' => $versions['nightly'] === [] ? null : end($versions['nightly']),
                ],
            ),
        );

        $mainTemplate = file_get_contents($this->etcDir . '/main.js.twig');
        if ($mainTemplate === false) {
            throw new RuntimeException('Cannot read main.js.twig');
        }

        file_put_contents(
            $this->projectRoot . '/main.js',
            render($mainTemplate, $versions),
        );
    }
}
