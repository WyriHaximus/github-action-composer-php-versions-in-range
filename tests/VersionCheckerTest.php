<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClientInterface;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionChecker;
use WyriHaximus\TestUtilities\TestCase;

use function json_encode;

final class VersionCheckerTest extends TestCase
{
    #[Test]
    public function imageExistsReturnsFalseOn404(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->once())
            ->method('get')
            ->willReturn([404, '']);

        $checker = new VersionChecker($client);
        self::assertFalse($checker->imageExists('8.5'));
    }

    #[Test]
    public function imageExistsReturnsTrueOn200(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->once())
            ->method('get')
            ->willReturn([200, '{}']);

        $checker = new VersionChecker($client);
        self::assertTrue($checker->imageExists('8.5'));
    }

    #[Test]
    public function imageExistsThrowsOnInvalidToken(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode([]));

        $checker = new VersionChecker($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid registry token');
        $checker->imageExists('8.5');
    }

    #[Test]
    public function imageExistsThrowsOnNon200Non404(): void
    {
        $client = self::createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('getRequired')
            ->willReturn(json_encode(['token' => 'test-token']));
        $client->expects($this->once())
            ->method('get')
            ->willReturn([500, '']);

        $checker = new VersionChecker($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Registry returned HTTP 500');
        $checker->imageExists('8.5');
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
        $this->expectExceptionMessageIsOrContains('Nightly build API returned HTTP 500');
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
