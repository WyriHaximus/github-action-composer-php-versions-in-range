<?php

declare(strict_types=1);

use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitTagFetcher;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClient;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionChecker;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionDataRunner;

require __DIR__ . '/../vendor/autoload.php';

new VersionDataRunner(new VersionChecker(new HttpClient()), new GitTagFetcher())->run(__DIR__ . '/../etc/versions.json');
