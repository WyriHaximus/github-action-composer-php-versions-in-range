<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionCheckerInterface;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionData;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionDataUpdater;

use function array_map;
use function chr;
use function implode;
use function in_array;
use function React\Async\async;
use function str_repeat;

final class VersionDataUpdaterTest extends AsyncTestCase
{
    private function tag(string $version): string
    {
        return str_repeat('a', 40) . "\trefs/tags/php-" . $version;
    }

    private function headerFor(string $major, string $minor): string
    {
        $quote = chr(34);

        return '#define PHP_MAJOR_VERSION ' . $major
            . "\n#define PHP_MINOR_VERSION " . $minor
            . "\n#define PHP_VERSION " . $quote . $major . '.' . $minor . '.0-dev' . $quote . "\n";
    }

    private function baseTags(): string
    {
        return implode(
            "\n",
            array_map(
                $this->tag(...),
                ['5.2.0', '5.3.0', '6.0.0', '7.0.0', '8.0.0', '8.4.3', '8.5.0RC1', '8.6.0alpha1'],
            ),
        );
    }

    private function baseData(): VersionData
    {
        return VersionData::fromJson('{"released":["5.3","7.0","8.0","8.3"],"upcoming":["8.3"],"nightly":["8.3"]}');
    }

    private function createChecker(bool $imageExists, bool $nightlyExists): VersionCheckerInterface
    {
        $checker = self::createMock(VersionCheckerInterface::class);
        $checker->method('imageExists')
            ->willReturn(async(static fn (): bool => $imageExists)());
        $checker->method('nightlyExists')
            ->willReturn($nightlyExists);

        return $checker;
    }

    private function createCheckerThrowing(): VersionCheckerInterface
    {
        $checker = self::createMock(VersionCheckerInterface::class);
        $checker->method('imageExists')
            ->willThrowException(new RuntimeException('network failure'));
        $checker->method('nightlyExists')
            ->willThrowException(new RuntimeException('network failure'));

        return $checker;
    }

    // -----------------------------------------------------------------------
    // State machine tests
    // -----------------------------------------------------------------------

    /** Test that initial update retains released versions. */
    #[Test]
    public function initialUpdateRetainsReleased(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );

        $result = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame(['5.3', '7.0', '8.0', '8.3', '8.4'], $result->released);
        self::assertSame([], $result->upcoming);
        self::assertSame([], $result->nightly);
    }

    #[Test]
    public function acceptsReleaseTagsStartingAtPhp56(): void
    {
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $tags    = implode(
            "\n",
            array_map($this->tag(...), ['7.0.0', '8.0.0', '8.4.3']),
        );

        $result = $updater->update($this->baseData(), $tags, $this->headerFor('8', '6'));

        self::assertSame(['5.3', '7.0', '8.0', '8.3', '8.4'], $result->released);
    }

    #[Test]
    public function acceptsPhpWatchTagsWithoutLegacyReleaseSentinels(): void
    {
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $tags    = implode(
            "\n",
            array_map($this->tag(...), ['8.4.0', '8.5.0', '8.6.0RC1']),
        );

        $result = $updater->update($this->baseData(), $tags, $this->headerFor('8', '7'));

        self::assertSame(['5.3', '7.0', '8.0', '8.3', '8.4', '8.5'], $result->released);
    }

    #[Test]
    public function rejectsUpstreamTagsWithoutStableReleases(): void
    {
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('PHP upstream tags contain no stable releases');

        $updater->update(
            $this->baseData(),
            $this->tag('8.6.0RC1'),
            $this->headerFor('8', '7'),
        );
    }

    #[Test]
    public function nightlyRequiresBuild(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame(['8.6'], $nightly->nightly);
    }

    #[Test]
    public function newImageWaitsADay(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame(['8.6'], $observed->nightly);
        self::assertSame([], $observed->upcoming);
    }

    #[Test]
    public function doNotPromoteBefore24Hours(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        // Still within 24 hours
        $earlyUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            186399,
        );
        $early        = $earlyUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame(['8.6'], $early->nightly);
        self::assertSame([], $early->upcoming);
    }

    #[Test]
    public function promoteAfter24Hours(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        $earlyUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            186399,
        );
        $early        = $earlyUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        // After 24 hours
        $promotedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            186400,
        );
        $promoted        = $promotedUpdater->update($early, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame([], $promoted->nightly);
        self::assertSame(['8.5', '8.6'], $promoted->upcoming);
    }

    #[Test]
    public function releaseRemovesPrereleaseEntries(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        $earlyUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            186399,
        );
        $early        = $earlyUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        $promotedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            186400,
        );
        $promoted        = $promotedUpdater->update($early, $this->baseTags(), $this->headerFor('8', '6'));

        // Add 8.6.0 release tag
        $releasedUpdater = new VersionDataUpdater(
            $this->createChecker(false, false),
            200000,
        );
        $released        = $releasedUpdater->update(
            $promoted,
            $this->baseTags() . "\n" . $this->tag('8.6.0'),
            $this->headerFor('8', '6'),
        );

        self::assertTrue(in_array('8.6', $released->released, true));
        self::assertFalse(in_array('8.6', $released->upcoming, true));
        self::assertSame([], $released->nightly);
    }

    #[Test]
    public function repeatedUpdateIsIdempotent(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        $earlyUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            186399,
        );
        $early        = $earlyUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        $promotedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            186400,
        );
        $promoted        = $promotedUpdater->update($early, $this->baseTags(), $this->headerFor('8', '6'));

        // Repeat
        $idempotentUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            200000,
        );
        $idempotent        = $idempotentUpdater->update($promoted, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame($promoted->released, $idempotent->released);
        self::assertSame($promoted->upcoming, $idempotent->upcoming);
        self::assertSame($promoted->nightly, $idempotent->nightly);
        self::assertSame($promoted->imageFirstSeen, $idempotent->imageFirstSeen);
    }

    #[Test]
    public function missingImageResetsObservation(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            $this->createChecker(false, false),
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        // Image disappears
        $lostUpdater = new VersionDataUpdater(
            $this->createChecker(false, true),
            186400,
        );
        $lost        = $lostUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        // Image reappears, but observation resets
        $againUpdater = new VersionDataUpdater(
            $this->createChecker(true, true),
            200000,
        );
        $again        = $againUpdater->update($lost, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame([], $again->upcoming);
    }

    #[Test]
    public function invalidHeaderFails(): void
    {
        $this->expectException(Throwable::class);
        new VersionDataUpdater(
            $this->createChecker(true, true),
            100000,
        )->update($this->baseData(), $this->baseTags(), 'invalid');
    }

    #[Test]
    public function registryFailurePropagates(): void
    {
        $this->expectException(Throwable::class);
        new VersionDataUpdater(
            $this->createCheckerThrowing(),
            100000,
        )->update($this->baseData(), $this->baseTags(), $this->headerFor('8', '6'));
    }

    #[Test]
    public function nightlyApiFailurePropagates(): void
    {
        $checker = self::createMock(VersionCheckerInterface::class);
        $checker->method('imageExists')
            ->willReturn(async(static fn (): false => false)());
        $checker->method('nightlyExists')
            ->willThrowException(new RuntimeException('network failure'));

        $this->expectException(Throwable::class);
        new VersionDataUpdater(
            $checker,
            100000,
        )->update($this->baseData(), $this->baseTags(), $this->headerFor('8', '6'));
    }

    // -----------------------------------------------------------------------
    // Smart check optimization tests
    // -----------------------------------------------------------------------

    /** Verify that nightlyExists is not called when a nightly version is already tracked. */
    #[Test]
    public function skipNightlyCheckWhenAlreadyTracked(): void
    {
        $data    = VersionData::fromJson('{"released":["5.3","7.0","8.0","8.3"],"upcoming":[],"nightly":["8.6"]}');
        $checker = self::createMock(VersionCheckerInterface::class);
        $checker->method('imageExists')
            ->willReturn(async(static fn (): bool => true)());
        // nightlyExists should NOT be called since 8.6 is already in nightly
        $checker->expects(self::never())->method('nightlyExists');

        $updater = new VersionDataUpdater($checker, 100000);
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame(['8.6'], $result->nightly);
    }

    /** Verify that imageExists is not called for versions already in upcoming. */
    #[Test]
    public function skipImageCheckWhenAlreadyInUpcoming(): void
    {
        $data            = VersionData::fromJson('{"released":["5.3","7.0","8.0","8.4"],"upcoming":["8.5"],"nightly":[]}');
        $checkedVersions = [];
        $checker         = self::createMock(VersionCheckerInterface::class);
        $checker->method('imageExists')
            ->willReturnCallback(
                static function (string $version) use (&$checkedVersions): PromiseInterface {
                    $checkedVersions[] = $version;

                    return async(static fn (): bool => false)();
                },
            );
        $checker->method('nightlyExists')
            ->willReturn(false);

        $updater = new VersionDataUpdater($checker, 100000);
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        // 8.5 should NOT be checked since it's already in upcoming
        self::assertFalse(in_array('8.5', $checkedVersions, true), '8.5 should not be checked when already in upcoming');
        self::assertTrue(in_array('8.5', $result->upcoming, true));
    }
}
