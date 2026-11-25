<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Generator;
use WyriHaximus\TestUtilities\TestCase;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_encode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class GeneratorTest extends TestCase
{
    private string $tempDir;
    private string $etcDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/generator-test-' . uniqid();
        mkdir($this->tempDir);
        $this->etcDir = $this->tempDir . '/etc';
        mkdir($this->etcDir);

        file_put_contents(
            $this->etcDir . '/versions.json',
            json_encode([
                'released' => ['8.0', '8.1'],
                'upcoming' => ['8.2'],
                'nightly' => ['8.3'],
            ], JSON_THROW_ON_ERROR),
        );

        file_put_contents(
            $this->etcDir . '/README.md.twig',
            'Upcoming: {{ upcoming }}',
        );

        file_put_contents(
            $this->etcDir . '/main.js.twig',
            'Released: {{ released|join(",") }}',
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir . '/README.md')) {
            unlink($this->tempDir . '/README.md');
        }

        if (file_exists($this->tempDir . '/main.js')) {
            unlink($this->tempDir . '/main.js');
        }

        if (file_exists($this->etcDir . '/versions.json')) {
            unlink($this->etcDir . '/versions.json');
        }

        if (file_exists($this->etcDir . '/README.md.twig')) {
            unlink($this->etcDir . '/README.md.twig');
        }

        if (file_exists($this->etcDir . '/main.js.twig')) {
            unlink($this->etcDir . '/main.js.twig');
        }

        rmdir($this->etcDir);
        rmdir($this->tempDir);
    }

    #[Test]
    public function generateCreatesReadmeAndMainJs(): void
    {
        new Generator($this->tempDir, $this->etcDir)->generate();

        self::assertFileExists($this->tempDir . '/README.md');
        self::assertFileExists($this->tempDir . '/main.js');

        $readme = file_get_contents($this->tempDir . '/README.md');
        self::assertNotFalse($readme);
        self::assertStringContainsString('8.2', $readme);

        $main = file_get_contents($this->tempDir . '/main.js');
        self::assertNotFalse($main);
        self::assertStringContainsString('8.0', $main);
        self::assertStringContainsString('8.1', $main);
    }

    #[Test]
    public function generateRemovesReleasedFromUpcomingAndNightly(): void
    {
        file_put_contents(
            $this->etcDir . '/versions.json',
            json_encode([
                'released' => ['8.0', '8.1'],
                'upcoming' => ['8.0', '8.2'],
                'nightly' => ['8.1', '8.3'],
            ], JSON_THROW_ON_ERROR),
        );

        file_put_contents(
            $this->etcDir . '/main.js.twig',
            'Upcoming: {{ upcoming|join(",") }}, Nightly: {{ nightly|join(",") }}',
        );

        new Generator($this->tempDir, $this->etcDir)->generate();

        $main = file_get_contents($this->tempDir . '/main.js');
        self::assertNotFalse($main);
        self::assertStringContainsString('Upcoming: 8.2', $main);
        self::assertStringContainsString('Nightly: 8.3', $main);
    }

    #[Test]
    public function generateSortsVersions(): void
    {
        file_put_contents(
            $this->etcDir . '/versions.json',
            json_encode([
                'released' => ['8.1', '8.0'],
                'upcoming' => [],
                'nightly' => [],
            ], JSON_THROW_ON_ERROR),
        );

        file_put_contents(
            $this->etcDir . '/main.js.twig',
            'Released: {{ released|join(",") }}',
        );

        new Generator($this->tempDir, $this->etcDir)->generate();

        $main = file_get_contents($this->tempDir . '/main.js');
        self::assertNotFalse($main);
        self::assertStringContainsString('Released: 8.0,8.1', $main);
    }

    #[Test]
    public function generateHandlesNullUpcomingAndNightly(): void
    {
        file_put_contents(
            $this->etcDir . '/versions.json',
            json_encode([
                'released' => ['8.0'],
                'upcoming' => [],
                'nightly' => [],
            ], JSON_THROW_ON_ERROR),
        );

        file_put_contents(
            $this->etcDir . '/README.md.twig',
            'Upcoming: {{ upcoming|default("none") }}, Nightly: {{ nightly|default("none") }}',
        );

        new Generator($this->tempDir, $this->etcDir)->generate();

        $readme = file_get_contents($this->tempDir . '/README.md');
        self::assertNotFalse($readme);
        self::assertStringContainsString('none', $readme);
    }

    #[Test]
    public function generateProducesNumericArrayKeys(): void
    {
        file_put_contents(
            $this->etcDir . '/versions.json',
            json_encode([
                'released' => ['8.0', '8.1'],
                'upcoming' => ['8.0', '8.2'],
                'nightly' => ['8.1', '8.3'],
            ], JSON_THROW_ON_ERROR),
        );

        file_put_contents(
            $this->etcDir . '/main.js.twig',
            'Upcoming: {{ upcoming|json_encode }}, Nightly: {{ nightly|json_encode }}',
        );

        new Generator($this->tempDir, $this->etcDir)->generate();

        $main = file_get_contents($this->tempDir . '/main.js');
        self::assertNotFalse($main);
        // array_values ensures numeric keys: `["8.2"]` instead of `{"0":"8.2"}`
        self::assertStringContainsString('&quot;8.2&quot;', $main);
        self::assertStringContainsString('&quot;8.3&quot;', $main);
    }

    #[Test]
    public function generateSetsNightlyNullWhenEmpty(): void
    {
        file_put_contents(
            $this->etcDir . '/versions.json',
            json_encode([
                'released' => ['8.0'],
                'upcoming' => ['8.1'],
                'nightly' => ['8.0'],
            ], JSON_THROW_ON_ERROR),
        );

        file_put_contents(
            $this->etcDir . '/README.md.twig',
            'Nightly is: {{ nightly is null ? "null" : nightly }}',
        );

        new Generator($this->tempDir, $this->etcDir)->generate();

        $readme = file_get_contents($this->tempDir . '/README.md');
        self::assertNotFalse($readme);
        // nightly should be null since 8.0 is already released
        self::assertStringContainsString('null', $readme);
    }
}
