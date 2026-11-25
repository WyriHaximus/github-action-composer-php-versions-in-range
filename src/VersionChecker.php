<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use React\Promise\PromiseInterface;
use RuntimeException;

use function array_key_exists;
use function count;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_quote;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

use const JSON_THROW_ON_ERROR;

final class VersionChecker implements VersionCheckerInterface
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

    /** @return PromiseInterface<bool> */
    public function imageExists(string $version): PromiseInterface
    {
        return async(function () use ($version): bool {
            $ghcrPromise      = $this->checkGhcr($version);
            $dockerHubPromise = $this->checkDockerHub($version);

            // Race both registries; whichever finds it first wins
            /** @var list<bool> $results */
            $results                        = await(all([$ghcrPromise, $dockerHubPromise]));
            [$ghcrExists, $dockerHubExists] = $results;

            return $ghcrExists || $dockerHubExists;
        })();
    }

    /** @return PromiseInterface<bool> */
    private function checkGhcr(string $version): PromiseInterface
    {
        $token    = $this->getToken();
        $result   = $this->httpClient->get(
            'https://ghcr.io/v2/wyrihaximusnet/php/manifests/' . $version . '-nts-alpine-dev',
            [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.oci.image.index.v1+json, application/vnd.oci.image.manifest.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.docker.distribution.manifest.v2+json',
            ],
        );
        [$status] = $result;

        if ($status === 404) {
            return async(static fn (): false => false)();
        }

        if ($status !== 200) {
            return async(static fn (): never => throw new RuntimeException('GHCR returned HTTP ' . $status . ' for version ' . $version))();
        }

        return async(static fn (): true => true)();
    }

    /** @return PromiseInterface<bool> */
    private function checkDockerHub(string $version): PromiseInterface
    {
        $result   = $this->httpClient->get(
            'https://hub.docker.com/v2/repositories/wyrihaximusnet/php/tags/' . $version . '-nts-alpine-dev',
        );
        [$status] = $result;

        if ($status === 404) {
            return async(static fn (): false => false)();
        }

        if ($status !== 200) {
            return async(static fn (): never => throw new RuntimeException('Docker Hub returned HTTP ' . $status . ' for version ' . $version))();
        }

        return async(static fn (): true => true)();
    }

    public function nightlyExists(string $version): bool
    {
        $result          = $this->httpClient->get(
            'https://api.github.com/repos/shivammathur/php-builder/releases/tags/' . $version,
            ['Accept' => 'application/vnd.github+json'],
        );
        [$status, $body] = $result;

        if ($status === 404) {
            return false;
        }

        if ($status !== 200) {
            throw new RuntimeException('Nightly build API returned HTTP ' . $status . ' for version ' . $version);
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
            throw new RuntimeException('Invalid nightly release response for version ' . $version);
        }

        if ($release['draft'] || $release['published_at'] === null) {
            return false;
        }

        return $this->checkAssets($release['id'], $version, 1);
    }

    private function getToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $body = $this->httpClient->getRequired('https://ghcr.io/token?scope=repository:wyrihaximusnet/php:pull');
        /** @var array<string, mixed> $auth */
        $auth = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (
            ! array_key_exists('token', $auth)
            || ! is_string($auth['token'])
            || $auth['token'] === ''
            || preg_match('/[\r\n]/', $auth['token']) !== 0
        ) {
            throw new RuntimeException('Invalid registry token');
        }

        $this->token = $auth['token'];

        return $this->token;
    }

    private function checkAssets(int $releaseId, string $version, int $page): bool
    {
        $body = $this->httpClient->getRequired(
            'https://api.github.com/repos/shivammathur/php-builder/releases/'
            . $releaseId
            . '/assets?per_page=100&page=' . $page,
        );

        /** @var list<array<string, mixed>> $assets */
        $assets = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        foreach ($assets as $asset) {
            if (
                ! array_key_exists('name', $asset)
                || ! array_key_exists('state', $asset)
                || ! array_key_exists('size', $asset)
            ) {
                throw new RuntimeException('Invalid nightly asset for version ' . $version);
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

        // Recurse to next page
        return $this->checkAssets($releaseId, $version, $page + 1);
    }
}
