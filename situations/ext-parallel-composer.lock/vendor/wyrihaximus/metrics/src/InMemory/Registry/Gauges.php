<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\InMemory\Registry;

use WyriHaximus\Metrics\Gauge as GaugeInterface;
use WyriHaximus\Metrics\InMemory\Gauge;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Label\Name;
use WyriHaximus\Metrics\Registry\Gauges as GaugesInterface;

use function array_key_exists;
use function array_map;
use function array_values;
use function implode;
use function strcmp;
use function usort;

final class Gauges implements GaugesInterface
{
    private const string SEPARATOR = '&%R*V^B)(*&^*%CEVR(B)&PY*';

    /** @var array<string> */
    private array $requiredLabelNames;
    /** @var array<Gauge> */
    private array $gauges = [];

    public function __construct(private string $name, private string $description, Name ...$requiredLabelNames)
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

    public function gauge(Label ...$labels): GaugeInterface
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

        if (! array_key_exists($key, $this->gauges)) {
            $this->gauges[$key] = new Gauge(...$labels);
        }

        return $this->gauges[$key];
    }

    /** @return iterable<Gauge> */
    public function gauges(): iterable
    {
        yield from array_values($this->gauges);
    }
}
