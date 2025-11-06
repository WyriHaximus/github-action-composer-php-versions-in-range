<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Label;

use InvalidArgumentException;

final class GivenLabelsDontMatchExpectedLabels extends InvalidArgumentException
{
    public const string MESSAGE = 'Given labels don\'t match expected labels';

    //phpcs:disable
    /** @var array<string> */
    public readonly array $expectedLabels;

    /** @var array<string> */
    public readonly array $labelNames;
    //phpcs:enable

    /**
     * @param array<string> $expectedLabels
     * @param array<string> $labelNames
     */
    public static function create(array $expectedLabels, array $labelNames): self
    {
        return new self(self::MESSAGE, $expectedLabels, $labelNames);
    }

    /**
     * @param array<string> $expectedLabels
     * @param array<string> $labelNames
     */
    private function __construct(string $message, array $expectedLabels, array $labelNames)
    {
        parent::__construct($message);

        $this->expectedLabels = $expectedLabels;
        $this->labelNames     = $labelNames;
    }
}
