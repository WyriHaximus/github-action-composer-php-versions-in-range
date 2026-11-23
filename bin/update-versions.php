<?php

declare(strict_types=1);

use React\Http\Browser;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitTagFetcher;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpClient;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionChecker;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionDataRunner;

require __DIR__ . '/../vendor/autoload.php';

$cacheDir = __DIR__ . '/../var/cache';
if (! is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$githubToken = (string) getenv('GITHUB_TOKEN');
$httpClient  = HttpClient::create(new Browser(), 3, 30, $githubToken);

$gitTagFetcher = GitTagFetcher::create($httpClient, $cacheDir);

new VersionDataRunner(new VersionChecker($httpClient), $gitTagFetcher)->run(__DIR__ . '/../etc/versions.json');
