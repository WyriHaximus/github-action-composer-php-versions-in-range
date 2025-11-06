<?php

declare(strict_types=1);

namespace ReactParallel\Contracts;

use Closure;
use WyriHaximus\PoolInfo\PoolInfoInterface;

interface PoolInterface extends PoolInfoInterface
{
    /**
     * @param (Closure():T)|(Closure(A0):T)|(Closure(A0,A1):T)|(Closure(A0,A1,A2):T)|(Closure(A0,A1,A2,A3):T)|(Closure(A0,A1,A2,A3,A4):T)|(Closure():void)|(Closure(A0):void)|(Closure(A0,A1):void)|(Closure(A0,A1,A2):void)|(Closure(A0,A1,A2,A3):void)|(Closure(A0,A1,A2,A3,A4):void) $callable
     * @param array{}|array{A0}|array{A0,A1}|array{A0,A1,A2}|array{A0,A1,A2,A3}|array{A0,A1,A2,A3,A4}                                                                                                                                                                                   $args
     *
     * @return (
     *      $callable is (Closure():T) ? T : (
     *          $callable is (Closure(A0):T) ? T : (
     *              $callable is (Closure(A0,A1):T) ? T : (
     *                  $callable is (Closure(A0,A1,A2):T) ? T : (
     *                      $callable is (Closure(A0,A1,A2,A3):T) ? T : (
     *                          $callable is (Closure(A0,A1,A2,A3,A4):T) ? T : null
     *                      )
     *                  )
     *              )
     *          )
     *      )
     * )
     *
     * @template T
     * @template A0 (any number of function arguments, see https://github.com/phpstan/phpstan/issues/8214)
     * @template A1
     * @template A2
     * @template A3
     * @template A4
     */
    public function run(Closure $callable, array $args = []): mixed;

    /**
     * Gently close every thread in the pool.
     *
     * @return bool True on success, or false when for some reason this call has been ignored.
     */
    public function close(): bool;

    /**
     * Kill every thread in the pool.
     *
     * @return bool True on success, or false when for some reason this call has been ignored.
     */
    public function kill(): bool;
}
