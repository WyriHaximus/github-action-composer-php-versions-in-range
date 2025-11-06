<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Label;

use WyriHaximus\Metrics\Label;

use function array_diff;
use function array_map;
use function count;

final class Utils
{
    /** @param array<string> $expectedLabels */
    public static function validate(array $expectedLabels, Label ...$labels): void
    {
        $labelNames = array_map(static fn (Label $label) => $label->name(), $labels);
        if (
            count(array_diff(
                $expectedLabels,
                $labelNames,
            )) > 0
        ) {
            throw GivenLabelsDontMatchExpectedLabels::create($expectedLabels, $labelNames);
        }
    }
}
