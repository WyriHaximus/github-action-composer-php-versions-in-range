<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionData;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionDataUpdater;
use WyriHaximus\TestUtilities\TestCase;

use function array_map;
use function chr;
use function implode;
use function in_array;
use function str_repeat;

final class VersionDataUpdaterTest extends TestCase
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

    // -----------------------------------------------------------------------
    // State machine tests
    // -----------------------------------------------------------------------

    /** Test that initial update retains released versions. */
    #[Test]
    public function initialUpdateRetainsReleased(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );

        $result = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame(['5.3', '7.0', '8.0', '8.3', '8.4'], $result->released);
        self::assertSame([], $result->upcoming);
        self::assertSame([], $result->nightly);
    }

    #[Test]
    public function nightlyRequiresBuild(): void
    {
        $data    = $this->baseData();
        $updater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
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
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
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
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        // Still within 24 hours
        $earlyUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
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
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        $earlyUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            186399,
        );
        $early        = $earlyUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        // After 24 hours
        $promotedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
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
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        $earlyUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            186399,
        );
        $early        = $earlyUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        $promotedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            186400,
        );
        $promoted        = $promotedUpdater->update($early, $this->baseTags(), $this->headerFor('8', '6'));

        // Add 8.6.0 release tag
        $releasedUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => false,
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
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        $earlyUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            186399,
        );
        $early        = $earlyUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        $promotedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            186400,
        );
        $promoted        = $promotedUpdater->update($early, $this->baseTags(), $this->headerFor('8', '6'));

        // Repeat
        $idempotentUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
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
            static fn (): bool => false,
            static fn (): bool => false,
            100000,
        );
        $result  = $updater->update($data, $this->baseTags(), $this->headerFor('8', '6'));

        $nightlyUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
            100000,
        );
        $nightly        = $nightlyUpdater->update($result, $this->baseTags(), $this->headerFor('8', '6'));

        $observedUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            100000,
        );
        $observed        = $observedUpdater->update($nightly, $this->baseTags(), $this->headerFor('8', '6'));

        // Image disappears
        $lostUpdater = new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => true,
            186400,
        );
        $lost        = $lostUpdater->update($observed, $this->baseTags(), $this->headerFor('8', '6'));

        // Image reappears, but observation resets
        $againUpdater = new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            200000,
        );
        $again        = $againUpdater->update($lost, $this->baseTags(), $this->headerFor('8', '6'));

        self::assertSame([], $again->upcoming);
    }

    // -----------------------------------------------------------------------
    // Error cases
    // -----------------------------------------------------------------------

    /** Test that incomplete tags fail. */
    #[Test]
    public function incompleteTagsFail(): void
    {
        $this->expectException(Throwable::class);
        new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            100000,
        )->update($this->baseData(), $this->tag('8.0.0'), $this->headerFor('8', '6'));
    }

    #[Test]
    public function invalidHeaderFails(): void
    {
        $this->expectException(Throwable::class);
        new VersionDataUpdater(
            static fn (): bool => true,
            static fn (): bool => true,
            100000,
        )->update($this->baseData(), $this->baseTags(), 'invalid');
    }

    #[Test]
    public function registryFailurePropagates(): void
    {
        $this->expectException(Throwable::class);
        new VersionDataUpdater(
            static fn (): bool => throw new RuntimeException('network failure'),
            static fn (): bool => true,
            100000,
        )->update($this->baseData(), $this->baseTags(), $this->headerFor('8', '6'));
    }

    #[Test]
    public function nightlyApiFailurePropagates(): void
    {
        $this->expectException(Throwable::class);
        new VersionDataUpdater(
            static fn (): bool => false,
            static fn (): bool => throw new RuntimeException('network failure'),
            100000,
        )->update($this->baseData(), $this->baseTags(), $this->headerFor('8', '6'));
    }
}
