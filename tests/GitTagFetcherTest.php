<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitTagFetcher;
use WyriHaximus\TestUtilities\TestCase;

final class GitTagFetcherTest extends TestCase
{
    #[Test]
    public function fetchReturnsTags(): void
    {
        $tags = new GitTagFetcher()->fetch();
        self::assertStringContainsString('refs/tags/php-', $tags);
    }

    #[Test]
    public function fetchContainsExpectedVersions(): void
    {
        $tags = new GitTagFetcher()->fetch();
        self::assertStringContainsString('refs/tags/php-8.0', $tags);
        self::assertStringContainsString('refs/tags/php-7.0', $tags);
        self::assertStringContainsString('refs/tags/php-5.3', $tags);
    }
}
