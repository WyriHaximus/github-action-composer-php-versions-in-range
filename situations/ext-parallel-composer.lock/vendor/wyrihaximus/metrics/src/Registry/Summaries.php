<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Registry;

use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Summary;

interface Summaries
{
    public function name(): string;

    public function description(): string;

    public function summary(Label ...$labels): Summary;

    /** @return iterable<Summary> */
    public function summaries(): iterable;
}
