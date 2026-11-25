<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClientInterface;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionChecker;

use function json_encode;
use function React\Async\await;
use function str_starts_with;

final class VersionCheckerTest extends AsyncTestCase
{
    #[Test]
    public function imageExistsReturnsFalseOn404(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturn([404, '']);

        $checker = new VersionChecker($client);
        self::assertFalse(await($checker->imageExists('8.5')));
    }

    #[Test]
    public function imageExistsReturnsTrueOnGhcr200(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                [200, '{}'],
                [404, ''],
            );

        $checker = new VersionChecker($client);
        self::assertTrue(await($checker->imageExists('8.5')));
    }

    #[Test]
    public function imageExistsSendsGhcrAuthorizationAsAStandardHeader(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $url, array $headers): array {
                if (str_starts_with($url, 'https://ghcr.io/')) {
                    self::assertSame([
                        'Authorization' => 'Bearer test-token',
                        'Accept' => 'application/vnd.oci.image.index.v1+json, application/vnd.oci.image.manifest.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.docker.distribution.manifest.v2+json',
                    ], $headers);

                    return [404, ''];
                }

                self::assertSame([], $headers);

                return [404, ''];
            });

        $checker = new VersionChecker($client);
        self::assertFalse(await($checker->imageExists('8.5')));
    }

    #[Test]
    public function imageExistsReturnsTrueOnDockerHub200(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                [404, ''],
                [200, '{}'],
            );

        $checker = new VersionChecker($client);
        self::assertTrue(await($checker->imageExists('8.5')));
    }

    #[Test]
    public function imageExistsThrowsOnInvalidToken(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode([]));
        $client->expects($this->never())
            ->method('get');

        $checker = new VersionChecker($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid registry token');
        await($checker->imageExists('8.5'));
    }

    #[Test]
    public function imageExistsThrowsOnGhcrNon200Non404(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                [500, ''],
                [404, ''],
            );

        $checker = new VersionChecker($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('GHCR returned HTTP 500 for version 8.5');
        await($checker->imageExists('8.5'));
    }

    #[Test]
    public function nightlyExistsReturnsFalseOn404(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([404, '']);

        $checker = new VersionChecker($client);
        self::assertFalse($checker->nightlyExists('8.5'));
    }

    #[Test]
    public function nightlyExistsReturnsFalseForDraft(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['id' => 1, 'draft' => true, 'published_at' => '2024-01-01'])]);

        $checker = new VersionChecker($client);
        self::assertFalse($checker->nightlyExists('8.5'));
    }

    #[Test]
    public function nightlyExistsReturnsFalseForNullPublishedAt(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['id' => 1, 'draft' => false, 'published_at' => null])]);

        $checker = new VersionChecker($client);
        self::assertFalse($checker->nightlyExists('8.5'));
    }

    #[Test]
    public function nightlyExistsThrowsOnNon200Non404(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([500, '']);

        $checker = new VersionChecker($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Nightly build API returned HTTP 500 for version 8.5');
        $checker->nightlyExists('8.5');
    }

    #[Test]
    public function nightlyExistsThrowsOnInvalidResponse(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['draft' => false, 'published_at' => '2024-01-01'])]);

        $checker = new VersionChecker($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid nightly release response');
        $checker->nightlyExists('8.5');
    }

    #[Test]
    public function nightlyExistsReturnsFalseWhenNoAssets(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['id' => 1, 'draft' => false, 'published_at' => '2024-01-01'])]);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn('[]');

        $checker = new VersionChecker($client);
        self::assertFalse($checker->nightlyExists('8.5'));
    }

    #[Test]
    public function nightlyExistsReturnsTrueWhenAssetMatches(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['id' => 1, 'draft' => false, 'published_at' => '2024-01-01'])]);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode([['name' => 'php_8.5+ubuntu.tar.zst', 'state' => 'uploaded', 'size' => 100]]));

        $checker = new VersionChecker($client);
        self::assertTrue($checker->nightlyExists('8.5'));
    }

    #[Test]
    public function nightlyExistsThrowsOnInvalidAsset(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturn([200, json_encode(['id' => 1, 'draft' => false, 'published_at' => '2024-01-01'])]);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode([['state' => 'uploaded', 'size' => 100]]));

        $checker = new VersionChecker($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid nightly asset');
        $checker->nightlyExists('8.5');
    }

    #[Test]
    public function getHttpClientReturnsInjectedClient(): void
    {
        $client  = self::createStub(HttpClientInterface::class);
        $checker = new VersionChecker($client);

        self::assertSame($client, $checker->getHttpClient());
    }
}
