<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use function WyriHaximus\Twig\render;

require dirname(__DIR__) . '/vendor/autoload.php';

$template = file_get_contents(__DIR__ . '/README.md.twig');

$renderedReadme = render($template, [
    'upcoming' => null,
    'nightly' => 8.5
]);

file_put_contents(dirname(__DIR__) . '/README.md', $renderedReadme);
