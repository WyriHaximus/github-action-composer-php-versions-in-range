<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\InMemory\Registry;

use WyriHaximus\Metrics\Counter as CounterInterface;
use WyriHaximus\Metrics\InMemory\Counter;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Label\Name;
use WyriHaximus\Metrics\Registry\Counters as CountersInterface;

use function array_key_exists;
use function array_map;
use function array_values;
use function implode;
use function strcmp;
use function usort;

final class Counters implements CountersInterface
{
    private const string SEPARATOR = '#@$%^&*()';

    /** @var array<string> */
    private array $requiredLabelNames;
    /** @var array<Counter> */
    private array $counters = [];

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

    public function counter(Label ...$labels): CounterInterface
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

        if (! array_key_exists($key, $this->counters)) {
            $this->counters[$key] = new Counter(...$labels);
        }

        return $this->counters[$key];
    }

    /** @return iterable<Counter> */
    public function counters(): iterable
    {
        yield from array_values($this->counters);
    }
}
