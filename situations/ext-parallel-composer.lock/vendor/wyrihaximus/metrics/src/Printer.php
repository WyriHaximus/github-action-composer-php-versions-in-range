<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics;

interface Printer
{
    public function print(PrintJob $print): string;
}
