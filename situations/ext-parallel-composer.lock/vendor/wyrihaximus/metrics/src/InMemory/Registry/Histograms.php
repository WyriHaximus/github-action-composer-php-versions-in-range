<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\InMemory\Registry;

use WyriHaximus\Metrics\Histogram as HistogramInterface;
use WyriHaximus\Metrics\Histogram\Buckets;
use WyriHaximus\Metrics\InMemory\Histogram;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Label\Name;
use WyriHaximus\Metrics\Registry\Histograms as HistogramsInterface;

use function array_key_exists;
use function array_map;
use function array_values;
use function implode;
use function strcmp;
use function usort;

final class Histograms implements HistogramsInterface
{
    private const string SEPARATOR = 'w34yw3[qi2c';

    /** @var array<string> */
    private array $requiredLabelNames;
    /** @var array<Histogram> */
    private array $histograms = [];

    public function __construct(private string $name, private string $description, private Buckets $buckets, Name ...$requiredLabelNames)
    {
        $this->requiredLabelNames = array_map(static fn (Name $name) => $name->name(), $requiredLabelNames);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function histogram(Label ...$labels): HistogramInterface
    {
        Label\Utils::validate($this->requiredLabelNames, ...$labels);

        usort($labels, static fn (Label $a, Label $b) => strcmp($a->name(), $b->name()));
        $key = implode(
            self::SEPARATOR,
            array_map(
                static fn (Label $label) => $label->value(),
                $labels,
            ),
        );

        if (! array_key_exists($key, $this->histograms)) {
            $this->histograms[$key] = new Histogram($this->buckets, ...$labels);
        }

        return $this->histograms[$key];
    }

    /** @return iterable<Histogram> */
    public function histograms(): iterable
    {
        yield from array_values($this->histograms);
    }
}
