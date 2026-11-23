<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use React\Promise\Deferred;
use Throwable;

use function array_any;
use function array_keys;
use function array_merge;
use function preg_replace;
use function React\Async\await;
use function str_starts_with;
use function strcasecmp;
use function strlen;
use function substr;
use function trim;

final readonly class HttpClient implements HttpClientInterface
{
    private function __construct(
        private Browser $browser,
        private int $maxRetries,
        private int $timeout,
        private string $githubToken,
    ) {
    }

    public static function create(Browser $browser, int $maxRetries = 3, int $timeout = 30, string $githubToken = ''): self
    {
        return new self($browser, $maxRetries, $timeout, $githubToken);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{int, string}
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->retry(0, $url, $headers);
    }

    public function getRequired(string $url): string
    {
        $result          = $this->get($url);
        [$status, $body] = $result;
        if ($status !== 200 || $body === '') {
            throw new HttpError('HTTP ' . $status . ' fetching ' . $url . self::responseBodyDetails($body), $status);
        }

        return $body;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{int, string}
     */
    private function retry(int $attempt, string $url, array $headers): array
    {
        if ($attempt > $this->maxRetries) {
            throw new HttpError('HTTP request failed after ' . ($this->maxRetries + 1) . ' attempts: ' . $url);
        }

        try {
            return $this->doGet($url, $headers);
        } catch (TransientHttpError $e) {
            if ($attempt < $this->maxRetries) {
                $delay    = $e->retryAfter > 0 ? $e->retryAfter : 1 << $attempt;
                $deferred = new Deferred();
                Loop::addTimer($delay, static fn () => $deferred->resolve(null));
                await($deferred->promise());

                return $this->retry($attempt + 1, $url, $headers);
            }

            throw $e;
        }
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{int, string}
     */
    private function doGet(string $url, array $headers = []): array
    {
        if ($this->githubToken !== '' && str_starts_with($url, 'https://api.github.com/')) {
            $hasAuthorization = array_any(array_keys($headers), static fn (string $header): bool => strcasecmp($header, 'Authorization') === 0);
            if (! $hasAuthorization) {
                $headers['Authorization'] = 'Bearer ' . $this->githubToken;
            }
        }

        $allHeaders = array_merge(['User-Agent' => 'php-version-updater'], $headers);

        $deferred     = new Deferred();
        $timeoutTimer = Loop::addTimer(
            $this->timeout,
            static fn () => $deferred->reject(TransientHttpError::create('Request to ' . $url . ' timed out')),
        );

        $this->browser->get($url, $allHeaders)->then(
            static function (ResponseInterface $response) use ($deferred, $url, $timeoutTimer): void {
                Loop::cancelTimer($timeoutTimer);
                self::handleResponse($deferred, $url, $response);
            },
            static function (Throwable $error) use ($deferred, $url, $timeoutTimer): void {
                Loop::cancelTimer($timeoutTimer);

                if ($error instanceof ResponseException) {
                    self::handleResponse($deferred, $url, $error->getResponse());

                    return;
                }

                $deferred->reject(
                    TransientHttpError::create('Request to ' . $url . ' failed: ' . $error->getMessage()),
                );
            },
        );

        return await($deferred->promise());
    }

    /** @param Deferred<array{int, string}> $deferred */
    private static function handleResponse(Deferred $deferred, string $url, ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();

        if ($status >= 500 || $status === 429 || $status === 403) {
            $deferred->reject(
                TransientHttpError::createWithRetryAfter(
                    self::responseErrorMessage($url, $response),
                    (int) $response->getHeaderLine('Retry-After'),
                ),
            );

            return;
        }

        if ($status === 404) {
            $deferred->resolve([$status, $body]);

            return;
        }

        if ($status >= 400) {
            $deferred->reject(new HttpError(self::responseErrorMessage($url, $response), $status));

            return;
        }

        $deferred->resolve([$status, $body]);
    }

    private static function responseErrorMessage(string $url, ResponseInterface $response): string
    {
        $message = 'HTTP request to ' . $url . ' failed: ' . $response->getStatusCode() . ' '
            . $response->getReasonPhrase() . self::responseBodyDetails((string) $response->getBody());

        foreach (
            [
                'WWW-Authenticate',
                'Retry-After',
                'X-GitHub-Request-Id',
                'X-RateLimit-Remaining',
                'X-RateLimit-Reset',
            ] as $header
        ) {
            $value = $response->getHeaderLine($header);
            if ($value === '') {
                continue;
            }

            $message .= '; ' . $header . ': ' . $value;
        }

        return $message;
    }

    private static function responseBodyDetails(string $body): string
    {
        $body = preg_replace('/[\r\n\t]+/', ' ', trim($body)) ?? trim($body);
        if ($body === '') {
            return '';
        }

        return '; response body: ' . substr($body, 0, 500) . (strlen($body) > 500 ? '... [truncated]' : '');
    }
}
