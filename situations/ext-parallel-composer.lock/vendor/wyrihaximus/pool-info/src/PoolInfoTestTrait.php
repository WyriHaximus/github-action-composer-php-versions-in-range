<?php

declare(strict_types=1);

namespace WyriHaximus\PoolInfo;

use function array_keys;
use function ksort;
use function WyriHaximus\iteratorOrArrayToArray;

trait PoolInfoTestTrait
{
    /** @return iterable<array<PoolInfoInterface>> */
    public function providePool(): iterable
    {
        yield [
            $this->poolFactory(),
        ];
    }

    /**
     * @test
     * @dataProvider providePool
     */
    public function assertAllItemsFromInfoAreReturnedFromInfoCall(PoolInfoInterface $poolInfo): void
    {
        $items = iteratorOrArrayToArray($poolInfo->info());

        ksort($items);

        self::assertSame(
            [
                Info::BUSY,
                Info::CALLS,
                Info::IDLE,
                Info::SIZE,
                Info::TOTAL,
            ],
            array_keys(
                $items,
            ),
        );
    }

    abstract protected function poolFactory(): PoolInfoInterface;
}
