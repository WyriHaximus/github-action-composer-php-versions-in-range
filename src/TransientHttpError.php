<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

final class TransientHttpError extends HttpError
{
    private function __construct(
        string $message,
        public readonly int $retryAfter,
    ) {
        parent::__construct($message);
    }

    public static function create(string $message): self
    {
        return new self($message, 0);
    }

    public static function createWithRetryAfter(string $message, int $retryAfter): self
    {
        return new self($message, $retryAfter);
    }
}
