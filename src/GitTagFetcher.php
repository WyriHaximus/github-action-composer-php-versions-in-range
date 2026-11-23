<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use Throwable;

use function array_key_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_bool;
use function is_file;
use function is_string;
use function json_decode;
use function preg_match;
use function str_repeat;
use function str_starts_with;
use function time;
use function trim;

use const JSON_THROW_ON_ERROR;

final readonly class GitTagFetcher implements GitTagFetcherInterface
{
    private function __construct(
        private HttpClientInterface $httpClient,
        private string $cacheDir,
    ) {
    }

    public static function create(HttpClientInterface $httpClient, string $cacheDir = ''): self
    {
        return new self($httpClient, $cacheDir);
    }

    public function fetch(): string
    {
        if ($this->cacheDir !== '') {
            $cacheFile = $this->cacheDir . '/git-tags.cache';
            $timeFile  = $this->cacheDir . '/git-tags.time';

            if (is_file($cacheFile) && is_file($timeFile)) {
                $cachedTimeContent = file_get_contents($timeFile);
                if ($cachedTimeContent !== false) {
                    $cachedTime = (int) $cachedTimeContent;
                    if (time() - $cachedTime <= 3600) {
                        $content = file_get_contents($cacheFile);
                        if ($content !== false && trim($content) !== '') {
                            return $content;
                        }
                    }
                }
            }
        }

        try {
            $tags = $this->fetchFromPhpWatch();
        } catch (Throwable $error) {
            throw new GitFetchError(
                'Unable to fetch PHP tags from PHP.Watch: ' . $error->getMessage(),
                (int) $error->getCode(),
                $error,
            );
        }

        if ($this->cacheDir !== '') {
            file_put_contents($this->cacheDir . '/git-tags.cache', $tags);
            file_put_contents($this->cacheDir . '/git-tags.time', (string) time());
        }

        return $tags;
    }

    private function fetchFromPhpWatch(): string
    {
        $result          = $this->httpClient->get('https://php.watch/api/v1/versions');
        [$status, $body] = $result;

        if ($status !== 200) {
            throw new GitFetchError('PHP.Watch API returned HTTP ' . $status);
        }

        /** @var mixed $response */
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($response) || ! array_key_exists('data', $response) || ! is_array($response['data'])) {
            throw new GitFetchError('Invalid PHP.Watch API response');
        }

        $tags = '';
        foreach ($response['data'] as $version) {
            if (
                ! is_array($version)
                || ! array_key_exists('name', $version)
                || ! array_key_exists('isFutureVersion', $version)
                || ! array_key_exists('isNextVersion', $version)
                || ! is_string($version['name'])
                || ! is_bool($version['isFutureVersion'])
                || ! is_bool($version['isNextVersion'])
                || preg_match('/^\d+\.\d+$/D', $version['name']) !== 1
            ) {
                continue;
            }

            if (str_starts_with($version['name'], '6.')) {
                continue;
            }

            if ($version['isNextVersion']) {
                // PHP.Watch reports the active development branch, not its release-candidate sequence.
                $tags .= str_repeat('0', 40) . "\trefs/tags/php-" . $version['name'] . ".0RC1\n";

                continue;
            }

            if ($version['isFutureVersion']) {
                continue;
            }

            // The updater needs tag names; PHP.Watch does not provide Git object hashes.
            $tags .= str_repeat('0', 40) . "\trefs/tags/php-" . $version['name'] . ".0\n";
        }

        if (trim($tags) === '') {
            throw new GitFetchError('PHP.Watch API returned no usable PHP versions');
        }

        return $tags;
    }
}
