<?php

declare(strict_types=1);

use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Generator;

require __DIR__ . '/../vendor/autoload.php';

new Generator(dirname(__DIR__), __DIR__ . '/../etc')->generate();
