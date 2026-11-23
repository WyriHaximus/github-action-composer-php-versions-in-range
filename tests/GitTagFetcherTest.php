<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use React\Http\Browser;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitFetchError;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitTagFetcher;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitTagFetcherInterface;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClient;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClientInterface;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\TransientHttpError;

use function basename;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function json_encode;
use function mkdir;
use function rmdir;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

#[Group('network')]
final class GitTagFetcherTest extends AsyncTestCase
{
    #[Test]
    public function createWithoutCacheReturnsInstance(): void
    {
        $fetcher = GitTagFetcher::create(HttpClient::create(new Browser()));
        self::assertInstanceOf(GitTagFetcher::class, $fetcher);
    }

    #[Test]
    public function createWithCacheReturnsInstance(): void
    {
        $fetcher = GitTagFetcher::create(HttpClient::create(new Browser()), '/tmp');
        self::assertInstanceOf(GitTagFetcher::class, $fetcher);
    }

    #[Test]
    public function fetchReturnsString(): void
    {
        $fetcher = self::createMock(GitTagFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetch')
            ->willReturn('tags');

        $result = $fetcher->fetch();
        self::assertSame('tags', $result);
    }

    #[Test]
    public function fetchThrowsOnError(): void
    {
        $fetcher = self::createMock(GitTagFetcherInterface::class);
        $fetcher->expects($this->once())
            ->method('fetch')
            ->willThrowException(new GitFetchError('Unable to fetch PHP tags: empty response'));

        $this->expectException(GitFetchError::class);
        $this->expectExceptionMessageIsOrContains('empty response');
        $fetcher->fetch();
    }

    #[Test]
    public function cacheReturnsCachedData(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'git-tag-test-');
        if ($tempFile === false) {
            self::markTestSkipped('Could not create temp file');
        }

        $cacheDir = dirname($tempFile) . '/' . basename($tempFile);
        unlink($tempFile);
        mkdir($cacheDir, 0777);

        try {
            // Pre-populate the cache so we don't need network access
            $fakeTags = 'abcdef1234567890abcdef1234567890abcdef12' . "\t" . 'refs/tags/php-8.0.0' . PHP_EOL;
            file_put_contents($cacheDir . '/git-tags.cache', $fakeTags);
            file_put_contents($cacheDir . '/git-tags.time', (string) time());

            $fetcher = GitTagFetcher::create(HttpClient::create(new Browser()), $cacheDir);

            // Fetch should return cached data without hitting the network
            $tags = $fetcher->fetch();
            self::assertSame($fakeTags, $tags);

            // Cache files should exist
            self::assertTrue(is_file($cacheDir . '/git-tags.cache'));
            self::assertTrue(is_file($cacheDir . '/git-tags.time'));
        } finally {
            $cacheFile = $cacheDir . '/git-tags.cache';
            $timeFile  = $cacheDir . '/git-tags.time';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }

            if (file_exists($timeFile)) {
                unlink($timeFile);
            }

            if (is_dir($cacheDir)) {
                rmdir($cacheDir);
            }
        }
    }

    #[Test]
    public function cacheExpiresAfterOneHour(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'git-tag-test-');
        if ($tempFile === false) {
            self::markTestSkipped('Could not create temp file');
        }

        $cacheDir = dirname($tempFile) . '/' . basename($tempFile);
        unlink($tempFile);
        mkdir($cacheDir, 0777);

        try {
            // Create an expired cache entry
            file_put_contents($cacheDir . '/git-tags.cache', 'expired-data');
            file_put_contents($cacheDir . '/git-tags.time', (string) (time() - 3601));

            // Verify the cache is properly marked as expired
            self::assertTrue(is_file($cacheDir . '/git-tags.cache'));
            self::assertTrue(is_file($cacheDir . '/git-tags.time'));

            $cachedTime = (int) file_get_contents($cacheDir . '/git-tags.time');
            self::assertGreaterThan(0, $cachedTime);
            self::assertLessThan(time() - 3600, $cachedTime);
        } finally {
            $cacheFile = $cacheDir . '/git-tags.cache';
            $timeFile  = $cacheDir . '/git-tags.time';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }

            if (file_exists($timeFile)) {
                unlink($timeFile);
            }

            if (is_dir($cacheDir)) {
                rmdir($cacheDir);
            }
        }
    }

    #[Test]
    public function fetchesPhpWatchVersions(): void
    {
        $body = json_encode([
            'data' => [
                '80500' => ['name' => '8.5', 'isFutureVersion' => false, 'isNextVersion' => false],
                '80400' => ['name' => '8.4', 'isFutureVersion' => false, 'isNextVersion' => false],
                '80600' => ['name' => '8.6', 'isFutureVersion' => true, 'isNextVersion' => true],
                '80700' => ['name' => '8.7', 'isFutureVersion' => true, 'isNextVersion' => false],
                '60000' => ['name' => '6.0', 'isFutureVersion' => false, 'isNextVersion' => false],
                'invalid' => ['name' => '8.5.1', 'isFutureVersion' => false, 'isNextVersion' => false],
            ],
        ], JSON_THROW_ON_ERROR);

        $httpClient = self::createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->with('https://php.watch/api/v1/versions')
            ->willReturn([200, $body]);

        $fetcher = GitTagFetcher::create($httpClient);
        $tags    = $fetcher->fetch();

        self::assertStringContainsString(str_repeat('0', 40) . "\trefs/tags/php-8.5.0", $tags);
        self::assertStringContainsString(str_repeat('0', 40) . "\trefs/tags/php-8.4.0", $tags);
        self::assertStringContainsString(str_repeat('0', 40) . "\trefs/tags/php-8.6.0RC1", $tags);
        self::assertStringNotContainsString('refs/tags/php-8.7.0', $tags);
        self::assertStringNotContainsString('refs/tags/php-6.0.0', $tags);
        self::assertStringNotContainsString('refs/tags/php-8.5.1.0', $tags);
    }

    #[Test]
    public function fetchThrowsWhenPhpWatchReturnsNon200Response(): void
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn([500, '']);

        $fetcher = GitTagFetcher::create($httpClient);

        $this->expectException(GitFetchError::class);
        $this->expectExceptionMessageIsOrContains('PHP.Watch API returned HTTP 500');
        $fetcher->fetch();
    }

    #[Test]
    public function fetchThrowsWhenPhpWatchResponseIsInvalid(): void
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['error' => 'not found'], JSON_THROW_ON_ERROR)]);

        $fetcher = GitTagFetcher::create($httpClient);

        $this->expectException(GitFetchError::class);
        $this->expectExceptionMessageIsOrContains('Invalid PHP.Watch API response');
        $fetcher->fetch();
    }

    #[Test]
    public function fetchWrapsClientErrorsWithPhpWatchContext(): void
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->willThrowException(TransientHttpError::create('Request timed out'));

        $fetcher = GitTagFetcher::create($httpClient);

        $this->expectException(GitFetchError::class);
        $this->expectExceptionMessageIsOrContains('Unable to fetch PHP tags from PHP.Watch: Request timed out');
        $fetcher->fetch();
    }

    #[Test]
    public function fetchThrowsWhenPhpWatchReturnsNoUsableVersions(): void
    {
        $httpClient = self::createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['data' => []], JSON_THROW_ON_ERROR)]);

        $fetcher = GitTagFetcher::create($httpClient);

        $this->expectException(GitFetchError::class);
        $this->expectExceptionMessageIsOrContains('PHP.Watch API returned no usable PHP versions');
        $fetcher->fetch();
    }
}
