<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

/**
 * Handles HTTP GET requests with headers.
 */
interface HttpClientInterface
{
    /**
     * @param list<string> $headers
     *
     * @return array{int, string}
     */
    public function get(string $url, array $headers = []): array;

    public function getRequired(string $url): string;
}
