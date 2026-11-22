<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitTagFetcherInterface;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClientInterface;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionChecker;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionDataRunner;
use WyriHaximus\TestUtilities\TestCase;

use function file_get_contents;
use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class VersionDataRunnerTest extends TestCase
{
    #[Test]
    public function runThrowsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Cannot read');

        $client = self::createStub(HttpClientInterface::class);
        $git    = self::createStub(GitTagFetcherInterface::class);
        $runner = new VersionDataRunner(new VersionChecker($client), $git);
        $runner->run('/nonexistent/path/versions.json');
    }

    #[Test]
    public function runThrowsOnInvalidJson(): void
    {
        $this->expectException(Throwable::class);

        $temp = tempnam(sys_get_temp_dir(), 'versions-');
        file_put_contents($temp, 'not json');

        try {
            $client = self::createStub(HttpClientInterface::class);
            $git    = self::createStub(GitTagFetcherInterface::class);
            $runner = new VersionDataRunner(new VersionChecker($client), $git);
            $runner->run($temp);
        } finally {
            unlink($temp);
        }
    }

    #[Test]
    public function runThrowsOnMissingRequiredFields(): void
    {
        $this->expectException(Throwable::class);

        $temp = tempnam(sys_get_temp_dir(), 'versions-');
        file_put_contents($temp, '{}');

        try {
            $client = self::createStub(HttpClientInterface::class);
            $git    = self::createStub(GitTagFetcherInterface::class);
            $runner = new VersionDataRunner(new VersionChecker($client), $git);
            $runner->run($temp);
        } finally {
            unlink($temp);
        }
    }

    #[Test]
    public function runReturnsEarlyWhenDataUnchanged(): void
    {
        $json = '{"released":["5.3","7.0","8.0"],"upcoming":[],"nightly":[]}';
        $temp = tempnam(sys_get_temp_dir(), 'versions-');
        file_put_contents($temp, $json);

        try {
            $client = self::createMock(HttpClientInterface::class);
            $client->expects($this->once())
                ->method('getRequired')
                ->willReturn("#define PHP_MAJOR_VERSION 8\n#define PHP_MINOR_VERSION 0\n#define PHP_VERSION \"8.0.0-dev\"\n");

            $git = self::createMock(GitTagFetcherInterface::class);
            $git->expects($this->once())
                ->method('fetch')
                ->willReturn($this->tag('5.3.0') . "\n" . $this->tag('7.0.0') . "\n" . $this->tag('8.0.0'));

            $runner = new VersionDataRunner(new VersionChecker($client), $git);
            $runner->run($temp);

            self::assertSame($json, file_get_contents($temp));
        } finally {
            unlink($temp);
        }
    }

    #[Test]
    public function runWritesUpdatedData(): void
    {
        $json = '{"released":["5.3","7.0"],"upcoming":[],"nightly":[]}';
        $temp = tempnam(sys_get_temp_dir(), 'versions-');
        file_put_contents($temp, $json);

        try {
            $client = self::createMock(HttpClientInterface::class);
            $client->expects($this->once())
                ->method('getRequired')
                ->willReturn("#define PHP_MAJOR_VERSION 8\n#define PHP_MINOR_VERSION 0\n#define PHP_VERSION \"8.0.0-dev\"\n");

            $git = self::createMock(GitTagFetcherInterface::class);
            $git->expects($this->once())
                ->method('fetch')
                ->willReturn($this->tag('5.3.0') . "\n" . $this->tag('7.0.0') . "\n" . $this->tag('8.0.0'));

            $runner = new VersionDataRunner(new VersionChecker($client), $git);
            $runner->run($temp);

            $updated = file_get_contents($temp);
            self::assertNotFalse($updated);
            self::assertStringContainsString('"8.0"', $updated);
        } finally {
            unlink($temp);
        }
    }

    #[Test]
    public function runThrowsOnGitFailure(): void
    {
        $this->expectException(RuntimeException::class);

        $json = '{"released":["5.3","7.0","8.0"],"upcoming":[],"nightly":[]}';
        $temp = tempnam(sys_get_temp_dir(), 'versions-');
        file_put_contents($temp, $json);

        try {
            $client = self::createStub(HttpClientInterface::class);

            $git = self::createMock(GitTagFetcherInterface::class);
            $git->expects($this->once())
                ->method('fetch')
                ->willThrowException(new RuntimeException('Git failed'));

            $runner = new VersionDataRunner(new VersionChecker($client), $git);
            $runner->run($temp);
        } finally {
            unlink($temp);
        }
    }

    private function tag(string $version): string
    {
        return str_repeat('a', 40) . "\trefs/tags/php-" . $version;
    }
}
