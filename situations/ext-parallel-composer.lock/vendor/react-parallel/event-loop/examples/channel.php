<?php declare(strict_types=1);

use parallel\Channel;
use React\EventLoop\Loop;
use ReactParallel\EventLoop\EventLoopBridge;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;

require_once __DIR__ . '/../vendor/autoload.php';

$eventLoopBridge = new EventLoopBridge();

Loop::futureTick(async(static function () use ($eventLoopBridge) {
    /** @var Channel<string> */
    $channel = new Channel(Channel::Infinite);

    Loop::futureTick(async(function () use ($channel): void {
        $channel->send('Hello World!');
        // Don't close the channel right after writing to it,
        // as it will be closed on both ends and the other
        // thread won't receive your message
        await(sleep(1));
        $channel->close();
    }));

    foreach ($eventLoopBridge->observe($channel) as $message) {
        echo $message, PHP_EOL;
    }
}));
