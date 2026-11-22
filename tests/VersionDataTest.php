<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\Test;

use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\VersionData;
use WyriHaximus\TestUtilities\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class VersionDataTest extends TestCase
{
    #[Test]
    public function fromJsonDefaultsMissingFieldsToEmpty(): void
    {
        foreach (['released', 'upcoming', 'nightly'] as $key) {
            $incomplete = ['released' => [], 'upcoming' => [], 'nightly' => []];
            unset($incomplete[$key]);

            $normalized = VersionData::fromJson(json_encode($incomplete, JSON_THROW_ON_ERROR));
            self::assertSame([], $normalized->{$key}, 'Missing ' . $key . ' defaults to an empty list');

            foreach ($incomplete as $existingKey => $value) {
                self::assertSame($value, $normalized->{$existingKey}, 'Existing list is preserved');
            }
        }
    }

    #[Test]
    public function fromJsonRejectsNullList(): void
    {
        foreach (['released', 'upcoming', 'nightly'] as $key) {
            $incomplete       = ['released' => [], 'upcoming' => [], 'nightly' => []];
            $incomplete[$key] = null;

            $this->expectException(Throwable::class);
            VersionData::fromJson(json_encode($incomplete, JSON_THROW_ON_ERROR));
        }
    }

    #[Test]
    public function fromJsonAllMissingListsAdded(): void
    {
        $allEmpty = VersionData::fromJson('{}');
        self::assertSame([], $allEmpty->released);
        self::assertSame([], $allEmpty->upcoming);
        self::assertSame([], $allEmpty->nightly);
    }

    #[Test]
    public function fromJsonRejectsInvalidJson(): void
    {
        $this->expectException(Throwable::class);
        VersionData::fromJson('not json');
    }

    #[Test]
    public function fromJsonRejectsInvalidData(): void
    {
        $this->expectException(Throwable::class);
        VersionData::fromJson('{"released":[],"upcoming":[],"nightly":[42]}');
    }

    #[Test]
    public function fromJsonRejectsInvalidObservation(): void
    {
        $this->expectException(Throwable::class);
        VersionData::fromJson('{"released":[],"upcoming":[],"nightly":[],"imageFirstSeen":{"8.6":"invalid"}}');
    }

    #[Test]
    public function toJsonProducesValidJson(): void
    {
        $data = VersionData::fromJson('{"released":["8.0"],"upcoming":[],"nightly":[]}');
        $json = $data->toJson();

        self::assertStringContainsString('"released"', $json);
        self::assertStringContainsString('"8.0"', $json);
        self::assertStringNotContainsString('"imageFirstSeen"', $json);
    }

    #[Test]
    public function toJsonIncludesImageFirstSeen(): void
    {
        $data = VersionData::fromJson('{"released":["8.0"],"upcoming":[],"nightly":[],"imageFirstSeen":{"8.1":12345}}');
        $json = $data->toJson();

        self::assertStringContainsString('"imageFirstSeen"', $json);
        self::assertStringContainsString('12345', $json);
    }

    #[Test]
    public function jsonSerializeReturnsArray(): void
    {
        $data       = VersionData::fromJson('{"released":["8.0"],"upcoming":["8.1"],"nightly":["8.2"]}');
        $serialized = $data->jsonSerialize();

        self::assertSame(['8.0'], $serialized['released']);
        self::assertSame(['8.1'], $serialized['upcoming']);
        self::assertSame(['8.2'], $serialized['nightly']);
    }

    #[Test]
    public function jsonSerializeOmitsEmptyImageFirstSeen(): void
    {
        $data       = VersionData::fromJson('{"released":["8.0"],"upcoming":[],"nightly":[]}');
        $serialized = $data->jsonSerialize();

        self::assertArrayNotHasKey('imageFirstSeen', $serialized);
    }
}
