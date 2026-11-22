<?php

declare(strict_types=1);

use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionDataLoader;

require __DIR__ . '/../vendor/autoload.php';

$loader = new VersionDataLoader(__DIR__ . '/../etc/versions.json');
$output = getenv('GITHUB_OUTPUT');
$loader->output(is_string($output) && $output !== '' ? $output : 'php://stdout');
