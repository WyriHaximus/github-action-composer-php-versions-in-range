<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;

use function array_merge;
use function count;
use function file_get_contents;
use function http_get_last_response_headers;
use function implode;
use function preg_match;
use function stream_context_create;

/**
 * Handles HTTP GET requests with headers.
 */
final class HttpClient implements HttpClientInterface
{
    /**
     * @param list<string> $headers
     *
     * @return array{int, string}
     */
    public function get(string $url, array $headers = []): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode(
                    "\r\n",
                    array_merge(['User-Agent: php-version-updater'], $headers),
                ),
                'timeout' => 30,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        /** @var list<string> $response */
        $response = http_get_last_response_headers();

        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . $url);
        }

        if (count($response) === 0) {
            throw new RuntimeException('HTTP request failed: ' . $url);
        }

        $statusResult = preg_match('/^HTTP\/\S+ (\d{3})/', $response[0], $status);
        if ($statusResult !== 1) {
            throw new RuntimeException('HTTP request failed: ' . $url);
        }

        return [(int) $status[1], $body];
    }

    public function getRequired(string $url): string
    {
        [$status, $body] = $this->get($url);
        if ($status !== 200 || $body === '') {
            throw new RuntimeException('HTTP ' . $status . ' fetching ' . $url);
        }

        return $body;
    }
}
