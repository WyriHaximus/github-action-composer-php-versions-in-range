<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClient;
use WyriHaximus\TestUtilities\TestCase;

use function strlen;

final class HttpClientTest extends TestCase
{
    #[Test]
    public function getReturnsStatusAndBody(): void
    {
        $client   = new HttpClient();
        [$status] = $client->get('https://www.example.com/');

        self::assertSame(200, $status);
    }

    #[Test]
    public function getRequiredReturnsBody(): void
    {
        $client = new HttpClient();
        $body   = $client->getRequired('https://www.example.com/');

        self::assertGreaterThan(0, strlen($body));
    }

    #[Test]
    public function getRequiredThrowsOnNon200(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('HTTP 404');

        new HttpClient()->getRequired('https://www.example.com/404');
    }
}
