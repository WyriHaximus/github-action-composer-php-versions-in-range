<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{int, string}
     */
    public function get(string $url, array $headers = []): array;

    public function getRequired(string $url): string;
}
