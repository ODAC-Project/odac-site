<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

/**
 * `latest.json`: the only mutable file of the distribution.
 *
 *   { "version": "2026.11.25", "fingerprint": "adaa3dab07d58ae1",
 *     "url": "odac-2026.11.25-adaa3dab07d58ae1.jsonl", "entries": 704 }
 *
 * Its content is validated strictly: the name it points to is used as a file
 * name on disk and in a URL.
 */
final class Pointer
{
    public const VERSION_PATTERN = '[0-9][0-9.]{0,31}';

    public const FINGERPRINT_PATTERN = '[0-9a-f]{16}';

    public const FILENAME_PATTERN = 'odac-'.self::VERSION_PATTERN.'-'.self::FINGERPRINT_PATTERN.'\.jsonl';

    private function __construct(
        public readonly string $version,
        public readonly string $fingerprint,
        public readonly string $filename,
        public readonly int $entries,
    ) {}

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new IngestionFailed('latest.json is not valid JSON: '.$e->getMessage());
        }
        if (! is_array($data)) {
            throw new IngestionFailed('latest.json is not a JSON object.');
        }

        $version = $data['version'] ?? null;
        $fingerprint = $data['fingerprint'] ?? null;
        $url = $data['url'] ?? null;
        $entries = $data['entries'] ?? null;

        if (! is_string($version) || ! preg_match('/^'.self::VERSION_PATTERN.'$/', $version)) {
            throw new IngestionFailed('latest.json: invalid "version".');
        }
        if (! is_string($fingerprint) || ! preg_match('/^'.self::FINGERPRINT_PATTERN.'$/', $fingerprint)) {
            throw new IngestionFailed('latest.json: invalid "fingerprint".');
        }
        if (! is_int($entries) || $entries < 1) {
            throw new IngestionFailed('latest.json: invalid "entries".');
        }

        $expected = "odac-{$version}-{$fingerprint}.jsonl";
        if (! is_string($url) || ($url !== $expected && $url !== './'.$expected)) {
            throw new IngestionFailed("latest.json: \"url\" must be \"{$expected}\" (the name carries the fingerprint).");
        }

        return new self($version, $fingerprint, $expected, $entries);
    }
}
