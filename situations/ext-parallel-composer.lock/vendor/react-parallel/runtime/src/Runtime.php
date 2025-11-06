<?php

declare(strict_types=1);

namespace ReactParallel\Runtime;

use Closure;
use parallel\Future;
use parallel\Runtime as ParallelRuntime;
use ReactParallel\EventLoop\EventLoopBridge;

use const WyriHaximus\Constants\ComposerAutoloader\LOCATION;

final readonly class Runtime
{
    private ParallelRuntime $runtime;

    public static function create(EventLoopBridge $eventLoopBridge): self
    {
        return new self($eventLoopBridge, LOCATION);
    }

    public function __construct(private EventLoopBridge $eventLoopBridge, string $autoload)
    {
        $this->runtime = new ParallelRuntime($autoload);
    }

    /**
     * @param (Closure():T)|(Closure(A1):T)|(Closure(A1,A2):T)|(Closure(A1,A2,A3):T)|(Closure(A1,A2,A3,A4):T)|(Closure(A1,A2,A3,A4,A5):T)|(Closure():void)|(Closure(A1):void)|(Closure(A1,A2):void)|(Closure(A1,A2,A3):void)|(Closure(A1,A2,A3,A4):void)|(Closure(A1,A2,A3,A4,A5):void) $callable
     * @param array{}|array{A1}|array{A1,A2}|array{A1,A2,A3}|array{A1,A2,A3,A4}|array{A1,A2,A3,A4,A5}                                                                                                                                                                                   $args
     *
     * @return (
     *      $callable is (Closure():T) ? T : (
     *          $callable is (Closure(A1):T) ? T : (
     *              $callable is (Closure(A1,A2):T) ? T : (
     *                  $callable is (Closure(A1,A2,A3):T) ? T : (
     *                      $callable is (Closure(A1,A2,A3,A4):T) ? T : (
     *                          $callable is (Closure(A1,A2,A3,A4,A5):T) ? T : null
     *                      )
     *                  )
     *              )
     *          )
     *      )
     * )
     *
     * @template T
     * @template A1 (any number of function arguments, see https://github.com/phpstan/phpstan/issues/8214)
     * @template A2
     * @template A3
     * @template A4
     * @template A5
     */
    public function run(Closure $callable, array $args = []): mixed
    {
        $future = $this->runtime->run($callable, $args);

        if ($future instanceof Future) {
            return $this->eventLoopBridge->await($future);
        }

        return null;
    }

    public function close(): void
    {
        $this->runtime->close();
    }

    public function kill(): void
    {
        $this->runtime->kill();
    }
}
