<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

use Generator;
use SplFileObject;

/**
 * Streams the JSON Lines artifact: one `{"type", "id", "data"}` per line.
 */
final class ArtifactReader
{
    public const TYPES = ['brand', 'chip_family', 'interface', 'vocabulary', 'device'];

    public const ID_PATTERN = '/^[a-z0-9][a-z0-9._-]*(\/[a-z0-9][a-z0-9._-]*)*$/';

    public const DEVICE_ID_PATTERN = '/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/';

    /** @return Generator<int, array{type: string, id: string, data: mixed}> */
    public static function entries(string $path): Generator
    {
        $file = new SplFileObject($path, 'r');
        $line = 0;
        while (! $file->eof()) {
            $raw = $file->fgets();
            $line++;
            if (trim($raw) === '') {
                continue;
            }
            try {
                $entry = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new IngestionFailed("Artifact line {$line}: invalid JSON ({$e->getMessage()}).");
            }
            if (! is_array($entry)
                || ! in_array($entry['type'] ?? null, self::TYPES, true)
                || ! is_string($entry['id'] ?? null)
                || ! preg_match(self::ID_PATTERN, $entry['id'])
                || ! array_key_exists('data', $entry)) {
                throw new IngestionFailed("Artifact line {$line}: expected {\"type\", \"id\", \"data\"} with a known type.");
            }
            // A vocabulary entry may be plain prose; brands and devices are objects.
            if (in_array($entry['type'], ['brand', 'device'], true) && ! is_array($entry['data'])) {
                throw new IngestionFailed("Artifact line {$line}: a {$entry['type']} carries an object.");
            }
            if ($entry['type'] === 'device' && ! preg_match(self::DEVICE_ID_PATTERN, $entry['id'])) {
                throw new IngestionFailed("Artifact line {$line}: a device id is \"<brand>/<model>\", got \"{$entry['id']}\".");
            }

            yield $line => $entry;
        }
    }

    /**
     * Full validation pass, before anything is stored.
     *
     * `levels` is the completeness vocabulary (`level => fields`), the only
     * criterion the site applies to place a device on a level.
     *
     * @return array{entries: int, devices: int, brands: array<string, array>, levels: array<int, list<string>>}
     */
    public static function scan(string $path): array
    {
        $count = 0;
        $devices = 0;
        $brands = [];
        $levels = [];
        $seen = [];
        foreach (self::entries($path) as $line => $entry) {
            $key = $entry['type'].':'.$entry['id'];
            if (isset($seen[$key])) {
                throw new IngestionFailed("Artifact line {$line}: duplicate {$entry['type']} \"{$entry['id']}\".");
            }
            $seen[$key] = true;
            $count++;
            if ($entry['type'] === 'brand') {
                $brands[$entry['id']] = $entry['data'];
            } elseif ($entry['type'] === 'vocabulary' && $entry['id'] === 'completeness') {
                $levels = self::levels($entry['data']);
            } elseif ($entry['type'] === 'device') {
                $devices++;
                if (! is_string($entry['data']['kind'] ?? null) || $entry['data']['kind'] === '') {
                    throw new IngestionFailed("Artifact line {$line}: device \"{$entry['id']}\" has no kind.");
                }
            }
        }

        return ['entries' => $count, 'devices' => $devices, 'brands' => $brands, 'levels' => $levels];
    }

    /**
     * `vocabulary/completeness` -> `[level => [field, ...]]`.
     *
     * @return array<int, list<string>>
     */
    public static function levels(mixed $vocabulary): array
    {
        $levels = [];
        foreach ((array) (is_array($vocabulary) ? ($vocabulary['levels'] ?? []) : []) as $level) {
            if (is_array($level) && is_int($level['level'] ?? null) && is_array($level['fields'] ?? null)) {
                $levels[$level['level']] = array_values(array_filter($level['fields'], 'is_string'));
            }
        }
        ksort($levels);

        return $levels;
    }
}
