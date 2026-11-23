<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use React\Http\Browser;
use React\Http\Message\Response;
use React\Http\Message\ResponseException;
use RuntimeException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClient;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpError;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\TransientHttpError;

use function React\Promise\reject;
use function React\Promise\resolve;
use function str_repeat;
use function str_starts_with;

final class HttpClientTest extends AsyncTestCase
{
    #[Test]
    public function createWithDefaults(): void
    {
        $client = HttpClient::create(new Browser());
        self::assertInstanceOf(HttpClient::class, $client);
    }

    #[Test]
    public function createWithCustomValues(): void
    {
        $client = HttpClient::create(new Browser(), 5, 60);
        self::assertInstanceOf(HttpClient::class, $client);
    }

    #[Test]
    public function sendsGithubTokenWithGithubApiRequests(): void
    {
        $browser = self::createMock(Browser::class);
        $browser->expects($this->exactly(2))
            ->method('get')
            ->with(
                self::callback(static fn (string $url): bool => str_starts_with($url, 'https://api.github.com/')),
                self::callback(static function (array $headers): bool {
                    self::assertSame('Bearer test-token', $headers['Authorization'] ?? null);

                    return true;
                }),
            )
            ->willReturnOnConsecutiveCalls(
                resolve(new Response(200, [], '{}')),
                resolve(new Response(200, [], '{}')),
            );

        $client = HttpClient::create($browser, 0, 1, 'test-token');

        self::assertSame([200, '{}'], $client->get('https://api.github.com/repos/php/php-src'));
        self::assertSame('{}', $client->getRequired('https://api.github.com/repos/shivammathur/php-builder/releases/1/assets'));
    }

    #[Test]
    public function includesHttpResponseDetailsWithoutExposingRequestToken(): void
    {
        $url     = 'https://api.github.com/repos/shivammathur/php-builder/releases/tags/8.7';
        $browser = self::createMock(Browser::class);
        $browser->expects($this->once())
            ->method('get')
            ->with($url, self::anything())
            ->willReturn(reject(new ResponseException(new Response(401, [
                'WWW-Authenticate' => 'Bearer realm="github"',
                'X-GitHub-Request-Id' => 'request-id',
            ], '{"message":"Bad credentials"}'))));

        $client = HttpClient::create($browser, 3, 1, 'secret-token');

        try {
            $client->get($url);
            self::fail('Expected unauthorized response to throw an HTTP error');
        } catch (HttpError $error) {
            self::assertSame(401, $error->getCode());
            self::assertStringContainsString($url, $error->getMessage());
            self::assertStringContainsString('401 Unauthorized', $error->getMessage());
            self::assertStringContainsString('{"message":"Bad credentials"}', $error->getMessage());
            self::assertStringContainsString('X-GitHub-Request-Id: request-id', $error->getMessage());
            self::assertStringNotContainsString('secret-token', $error->getMessage());
        }
    }

    #[Test]
    public function returnsNotFoundStatusAndKeepsRequiredRequestsStrict(): void
    {
        $url     = 'https://ghcr.io/v2/wyrihaximusnet/php/manifests/8.7-nts-alpine-dev';
        $browser = self::createMock(Browser::class);
        $browser->expects($this->exactly(2))
            ->method('get')
            ->with($url, self::anything())
            ->willReturnOnConsecutiveCalls(
                reject(new ResponseException(new Response(404, [], 'manifest unknown'))),
                reject(new ResponseException(new Response(404, [], 'manifest unknown'))),
            );

        $client = HttpClient::create($browser, 0, 1);

        self::assertSame([404, 'manifest unknown'], $client->get($url));

        try {
            $client->getRequired($url);
            self::fail('Expected required request to throw on a missing resource');
        } catch (HttpError $error) {
            self::assertSame(404, $error->getCode());
            self::assertStringContainsString($url, $error->getMessage());
        }
    }

    #[Test]
    public function truncatesLongHttpErrorBodies(): void
    {
        $url     = 'https://api.github.com/example';
        $body    = str_repeat('x', 600);
        $browser = self::createMock(Browser::class);
        $browser->expects($this->once())
            ->method('get')
            ->with($url, self::anything())
            ->willReturn(reject(new ResponseException(new Response(503, [], $body))));

        $client = HttpClient::create($browser, 0, 1);

        try {
            $client->get($url);
            self::fail('Expected server error to throw a transient HTTP error');
        } catch (TransientHttpError $error) {
            self::assertStringContainsString(str_repeat('x', 500) . '... [truncated]', $error->getMessage());
        }
    }

    #[Test]
    public function includesUrlWhenRequiredRequestReturnsEmptyResponse(): void
    {
        $url     = 'https://example.com/empty';
        $browser = self::createMock(Browser::class);
        $browser->expects($this->once())
            ->method('get')
            ->with($url, self::anything())
            ->willReturn(resolve(new Response(302, [], '')));

        $client = HttpClient::create($browser, 0, 1);

        try {
            $client->getRequired($url);
            self::fail('Expected an empty required response to throw an HTTP error');
        } catch (HttpError $error) {
            self::assertSame(302, $error->getCode());
            self::assertSame('HTTP 302 fetching ' . $url, $error->getMessage());
        }
    }

    #[Test]
    public function includesRequestUrlForConnectionFailures(): void
    {
        $url     = 'https://api.github.com/example';
        $browser = self::createMock(Browser::class);
        $browser->expects($this->once())
            ->method('get')
            ->with($url, self::anything())
            ->willReturn(reject(new RuntimeException('Connection refused')));

        $client = HttpClient::create($browser, 0, 1);

        try {
            $client->get($url);
            self::fail('Expected connection failure to throw a transient HTTP error');
        } catch (TransientHttpError $error) {
            self::assertStringContainsString($url, $error->getMessage());
            self::assertStringContainsString('Connection refused', $error->getMessage());
        }
    }

    #[Test]
    public function transientHttpErrorWithRetryAfter(): void
    {
        $error = TransientHttpError::createWithRetryAfter('Rate limited', 60);
        self::assertSame('Rate limited', $error->getMessage());
        self::assertSame(60, $error->retryAfter);
    }

    #[Test]
    public function transientHttpErrorWithDefaultRetryAfter(): void
    {
        $error = TransientHttpError::create('Timeout');
        self::assertSame('Timeout', $error->getMessage());
        self::assertSame(0, $error->retryAfter);
    }
}
