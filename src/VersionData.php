<?php

declare(strict_types=1);

namespace WyriHaximus\GitHubAction\ComposerPhpVersionsInRange;

use JsonSerializable;
use RuntimeException;

use function array_is_list;
use function array_key_exists;
use function count;
use function intdiv;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function preg_replace_callback;
use function str_repeat;
use function strlen;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final readonly class VersionData implements JsonSerializable
{
    /**
     * @param list<string>       $released
     * @param list<string>       $upcoming
     * @param list<string>       $nightly
     * @param array<string, int> $imageFirstSeen
     */
    public function __construct(
        public array $released,
        public array $upcoming,
        public array $nightly,
        public array $imageFirstSeen,
    ) {
    }

    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $fields = ['released', 'upcoming', 'nightly'];
        $values = [];

        foreach ($fields as $key) {
            if (! array_key_exists($key, $data)) {
                $values[$key] = [];
                continue;
            }

            if ($data[$key] === null || ! is_array($data[$key]) || ! array_is_list($data[$key])) {
                throw new RuntimeException('Invalid version list: ' . $key);
            }

            foreach ($data[$key] as $version) {
                if (! is_string($version)) {
                    throw new RuntimeException('Invalid version in ' . $key);
                }

                if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)$/D', $version) !== 1) {
                    throw new RuntimeException('Invalid version in ' . $key);
                }
            }

            $values[$key] = $data[$key];
        }

        $imageFirstSeen = [];
        if (array_key_exists('imageFirstSeen', $data) && is_array($data['imageFirstSeen'])) {
            foreach ($data['imageFirstSeen'] as $version => $timestamp) {
                if (! is_int($timestamp)) {
                    throw new RuntimeException('Invalid image observation');
                }

                if ($timestamp < 0) {
                    throw new RuntimeException('Invalid image observation');
                }

                $versionString = (string) $version;
                if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)$/D', $versionString) !== 1) {
                    throw new RuntimeException('Invalid image observation');
                }

                $imageFirstSeen[$versionString] = $timestamp;
            }
        }

        return new self(
            $values['released'],
            $values['upcoming'],
            $values['nightly'],
            /** @suppress PhanTypeArrayDimAccessor */
            $imageFirstSeen,
        );
    }

    public function toJson(string $indent = '  '): string
    {
        $json     = json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $replacer = static fn (array $match): string => str_repeat($indent, intdiv(strlen($match[0]), 2));
        $result   = preg_replace_callback('/^ +/m', $replacer, $json);

        if ($result === null) {
            throw new RuntimeException('Failed to format version JSON');
        }

        return $result . "\n";
    }

    /** @return array<string, list<string>|array<string, int>> */
    public function jsonSerialize(): array
    {
        $data = [
            'released' => $this->released,
            'upcoming' => $this->upcoming,
            'nightly' => $this->nightly,
        ];

        if (count($this->imageFirstSeen) > 0) {
            $data['imageFirstSeen'] = $this->imageFirstSeen;
        }

        return $data;
    }
}
