<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use RuntimeException;

use function array_key_exists;
use function count;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_quote;

use const JSON_THROW_ON_ERROR;

/**
 * Checks whether a PHP version image exists in the registry and whether a nightly build exists.
 */
final class VersionChecker
{
    private string|null $token = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    public function imageExists(string $version): bool
    {
        if ($this->token === null) {
            /** @var array<string, mixed> $auth */
            $auth = json_decode(
                $this->httpClient->getRequired('https://ghcr.io/token?scope=repository:wyrihaximusnet/php:pull'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            if (
                ! array_key_exists('token', $auth)
                || ! is_string($auth['token'])
                || $auth['token'] === ''
                || preg_match('/[\r\n]/', $auth['token']) !== 0
            ) {
                throw new RuntimeException('Invalid registry token');
            }

            $this->token = $auth['token'];
        }

        [$status] = $this->httpClient->get(
            'https://ghcr.io/v2/wyrihaximusnet/php/manifests/' . $version . '-nts-alpine-dev',
            [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/vnd.oci.image.index.v1+json, application/vnd.oci.image.manifest.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.docker.distribution.manifest.v2+json',
            ],
        );

        if ($status === 404) {
            return false;
        }

        if ($status !== 200) {
            throw new RuntimeException('Registry returned HTTP ' . $status . ' for ' . $version);
        }

        return true;
    }

    public function nightlyExists(string $version): bool
    {
        [$status, $body] = $this->httpClient->get(
            'https://api.github.com/repos/shivammathur/php-builder/releases/tags/' . $version,
            ['Accept: application/vnd.github+json'],
        );

        if ($status === 404) {
            return false;
        }

        if ($status !== 200) {
            throw new RuntimeException('Nightly build API returned HTTP ' . $status);
        }

        /** @var array<string, mixed> $release */
        $release = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (
            ! array_key_exists('id', $release)
            || ! array_key_exists('draft', $release)
            || ! array_key_exists('published_at', $release)
            || ! is_int($release['id'])
            || ! is_bool($release['draft'])
        ) {
            throw new RuntimeException('Invalid nightly release response');
        }

        if ($release['draft'] || $release['published_at'] === null) {
            return false;
        }

        for ($page = 1;; $page++) {
            /** @var list<array<string, mixed>> $assets */
            $assets = json_decode(
                $this->httpClient->getRequired(
                    'https://api.github.com/repos/shivammathur/php-builder/releases/'
                    . $release['id']
                    . '/assets?per_page=100&page=' . $page,
                ),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            foreach ($assets as $asset) {
                if (
                    ! array_key_exists('name', $asset)
                    || ! array_key_exists('state', $asset)
                    || ! array_key_exists('size', $asset)
                ) {
                    throw new RuntimeException('Invalid nightly asset');
                }

                if (
                    $asset['state'] !== 'uploaded'
                    || ! is_int($asset['size'])
                    || $asset['size'] <= 0
                ) {
                    continue;
                }

                $assetName = is_string($asset['name']) ? $asset['name'] : '';
                if (
                    preg_match(
                        '/^php_' . preg_quote($version, '/') . '.*\+(?:ubuntu|debian).*\.tar\.zst$/iD',
                        $assetName,
                    ) === 1
                ) {
                    return true;
                }
            }

            if (count($assets) < 100) {
                return false;
            }
        }
    }
}
