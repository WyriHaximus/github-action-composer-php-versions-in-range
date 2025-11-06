<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

use Lcobucci\Clock\SystemClock;
use WyriHaximus\Metrics\Histogram\Buckets;
use WyriHaximus\Metrics\Summary\Quantiles;

final class Factory
{
    private const array DEFAULT_BUCKETS = [
        0.001,
        0.0025,
        0.005,
        0.0075,
        0.01,
        0.025,
        0.05,
        0.075,
        0.1,
        0.25,
        0.5,
        0.75,
        1,
        2.5,
        5,
        7.5,
        10,
    ];

    private const array DEFAULT_QUANTILES = [
        0.1,
        0.5,
        0.9,
        0.99,
    ];

    public static function create(): Registry
    {
        return self::createWithConfiguration(Configuration::create()->withClock(SystemClock::fromUTC()));
    }

    public static function createWithConfiguration(Configuration $configuration): Registry
    {
        return new InMemory\Registry($configuration);
    }

    public static function defaultBuckets(): Buckets
    {
        return new Buckets(...self::DEFAULT_BUCKETS);
    }

    public static function defaultQuantiles(): Quantiles
    {
        return new Quantiles(...self::DEFAULT_QUANTILES);
    }
}
