<?php

declare(strict_types=1);

use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Histogram\Buckets;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Label\Name;
use WyriHaximus\Metrics\Printer\Prometheus;

require 'vendor/autoload.php';

$registry = Factory::create();

$counter = $registry->counter('counter', 'simple counter counting things', new Name('label'));
$gauge = $registry->gauge('counter', 'simple counter counting things', new Name('label'));
$histogram = $registry->histogram('histogram', 'simple histogram histogramming things', new Buckets(0.5, 1, 2.5, 5, 10), new Name('label'));

for ($label = 'a'; $label !== 'aa'; $label++) {
    $counter->counter(new Label('label', $label))->incr();
    $gauge->gauge(new Label('label', $label))->incr();
    for ($i = 0; $i < 10000; $i++) {
        $histogram->histogram(new Label('label', $label))->observe(random_int(100, 4000) / 1000);
        $histogram->histogram(new Label('label', $label))->observe(random_int(11, 1900) / 100);
    }
}

echo $registry->print(new Prometheus()), PHP_EOL;
