<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionDataLoader;
use WyriHaximus\TestUtilities\TestCase;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class VersionDataLoaderTest extends TestCase
{
    private string $tempFile;
    private VersionDataLoader $loader;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'versions-');
        file_put_contents(
            $this->tempFile,
            json_encode([
                'released' => ['8.0', '8.1', '8.2'],
                'upcoming' => ['8.3', '8.4'],
                'nightly' => ['8.5'],
            ], JSON_THROW_ON_ERROR),
        );
        $this->loader = new VersionDataLoader($this->tempFile);
    }

    protected function tearDown(): void
    {
        if (! file_exists($this->tempFile)) {
            return;
        }

        unlink($this->tempFile);
    }

    #[Test]
    public function getHighest(): void
    {
        self::assertSame('8.2', $this->loader->getHighest());
    }

    #[Test]
    public function getHighestUpcoming(): void
    {
        self::assertSame('8.4', $this->loader->getHighestUpcoming());
    }

    #[Test]
    public function getNightly(): void
    {
        self::assertSame('8.5', $this->loader->getNightly());
    }

    #[Test]
    public function fallsBackToHighestWhenNoUnreleased(): void
    {
        file_put_contents(
            $this->tempFile,
            json_encode([
                'released' => ['8.0', '8.1'],
                'upcoming' => ['8.1'],
                'nightly' => ['8.1'],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertSame('8.1', $this->loader->getHighestUpcoming());
        self::assertSame('8.1', $this->loader->getNightly());
    }

    #[Test]
    public function outputWritesToFile(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'output-');
        try {
            $this->loader->output($outputFile);
            $content = file_get_contents($outputFile);
            if ($content === false) {
                self::fail('Could not read output file');
            }

            self::assertStringContainsString('highest=8.2', $content);
            self::assertStringContainsString('highestUpcoming=8.4', $content);
            self::assertStringContainsString('nightly=8.5', $content);
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }
}
