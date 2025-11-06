<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\InMemory\Registry;

use WyriHaximus\Metrics\Configuration;
use WyriHaximus\Metrics\InMemory\Summary;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Label\Name;
use WyriHaximus\Metrics\Registry\Summaries as SummariesInterface;
use WyriHaximus\Metrics\Summary\Quantiles;

use function array_key_exists;
use function array_map;
use function array_values;
use function implode;
use function strcmp;
use function usort;

final class Summaries implements SummariesInterface
{
    private const string SEPARATOR = 'aefnpawpijo%*&^)(3w4q1japwe';

    /** @var array<string> */
    private array $requiredLabelNames;
    /** @var array<Summary> */
    private array $summaries = [];

    public function __construct(private Configuration $configuration, private string $name, private string $description, private Quantiles $quantiles, Name ...$requiredLabelNames)
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

    public function summary(Label ...$labels): Summary
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

        if (! array_key_exists($key, $this->summaries)) {
            $this->summaries[$key] = new Summary($this->configuration, $this->quantiles, ...$labels);
        }

        return $this->summaries[$key];
    }

    /** @return iterable<Summary> */
    public function summaries(): iterable
    {
        yield from array_values($this->summaries);
    }
}
