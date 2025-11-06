<?php

declare(strict_types=1);

namespace WyriHaximus;

use function is_array;
use function iterator_to_array;

/**
 * @param iterable<mixed> $iterable
 *
 * @return array<mixed>
 */
function iteratorOrArrayToArray(iterable $iterable): array
{
    if (is_array($iterable)) {
        return $iterable;
    }

    return iterator_to_array($iterable);
}
