<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

interface GitTagFetcherInterface
{
    public function fetch(): string;
}
