<?php

declare(strict_types=1);

namespace WyriHaximus\Metrics\Configuration;

final class Summary
{
    private const int BUCKET_COUNT            = 10;
    private const string BUCKET_TIME_TEMPLATE = 'YzGi';

    private int $buckets               = self::BUCKET_COUNT;
    private string $bucketTimeTemplate = self::BUCKET_TIME_TEMPLATE;

    public function withBucketCount(int $buckets): self
    {
        $clone          = clone $this;
        $clone->buckets = $buckets;

        return $clone;
    }

    public function bucketCount(): int
    {
        return $this->buckets;
    }

    public function withBucketTimeTemplate(string $bucketTimeTemplate): self
    {
        $clone                     = clone $this;
        $clone->bucketTimeTemplate = $bucketTimeTemplate;

        return $clone;
    }

    public function bucketTimeTemplate(): string
    {
        return $this->bucketTimeTemplate;
    }
}
