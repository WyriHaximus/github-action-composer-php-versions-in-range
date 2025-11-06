<?php

use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Registry;

/**
 * @Revs({1, 8, 64, 4096})
 */
final class CounterBench
{
    private const LAST_LABEL = 'zz';

    private Registry $registry;

    public function __construct()
    {
        $this->registry = new Registry();
    }

    public function benchIncr()
    {
        for ($label = 'a'; $label !== self::LAST_LABEL; $label++) {
            $this->registry->counter('counter', 'counter', new Label\Name('label'))->counter(new Label('label', $label))->incr();
        }
    }

    public function benchIncrBy()
    {
        for ($label = 'a'; $label !== self::LAST_LABEL; $label++) {
            $this->registry->counter('counter', 'counter', new Label\Name('label'))->counter(new Label('label', $label))->incrBy(100);
        }
    }

    public function benchIncrTo()
    {
        for ($label = 'a'; $label !== self::LAST_LABEL; $label++) {
            $this->registry->counter('counter', 'counter', new Label\Name('label'))->counter(new Label('label', $label))->incrTo(100);
        }
    }
}
