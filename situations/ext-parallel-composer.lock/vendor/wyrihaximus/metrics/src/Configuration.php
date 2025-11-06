<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use WyriHaximus\Metrics\Configuration\Summary as ConfigurationSummary;

final class Configuration
{
    private Clock $clock;
    private ConfigurationSummary $summary;

    public static function create(): Configuration
    {
        return new self();
    }

    private function __construct()
    {
        $this->clock   = SystemClock::fromUTC();
        $this->summary = new ConfigurationSummary();
    }

    public function withClock(Clock $clock): Configuration
    {
        $clone        = clone $this;
        $clone->clock = $clock;

        return $clone;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function withSummary(ConfigurationSummary $summary): Configuration
    {
        $clone          = clone $this;
        $clone->summary = $summary;

        return $clone;
    }

    public function summary(): ConfigurationSummary
    {
        return $this->summary;
    }
}
