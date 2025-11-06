<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

final class PrintJob
{
    /**
     * @param array<Registry\Counters>   $counters
     * @param array<Registry\Gauges>     $gauges
     * @param array<Registry\Histograms> $histograms
     * @param array<Registry\Summaries>  $summaries
     */
    public function __construct(
        public readonly array $counters,
        public readonly array $gauges,
        public readonly array $histograms,
        public readonly array $summaries,
    ) {
    }
}
