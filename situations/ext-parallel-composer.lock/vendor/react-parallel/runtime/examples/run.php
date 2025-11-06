<?php

use ReactParallel\Runtime\Runtime;
use ReactParallel\EventLoop\EventLoopBridge;

require __DIR__ . '/../vendor/autoload.php';

$runtime = Runtime::create(new EventLoopBridge());

echo $runtime->run(function (): int {
    sleep(3);

    return 3;
}), PHP_EOL;
