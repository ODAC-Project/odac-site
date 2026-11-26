<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Actions\Odac;

use App\Models\OdacAlias;
use App\Models\OdacDevice;
use App\Models\OdacSnapshot;
use App\Support\Odac\ArtifactReader;
use App\Support\Odac\Completeness;
use Illuminate\Support\Str;

/**
 * Rebuilds the readable projection (devices, redirecting aliases) from a
 * stored artifact. Must run inside the transaction that makes the snapshot
 * current, so that readers never see half a catalogue.
 */
final class ProjectSnapshot
{
    private const CHUNK = 200;

    /**
     * @param  array<string, array>  $brands
     * @param  array<int, list<string>>  $levels  the completeness vocabulary
     */
    public function __invoke(OdacSnapshot $snapshot, array $brands, array $levels = []): int
    {
        OdacAlias::query()->delete();
        OdacDevice::query()->delete();

        $devices = [];
        $aliases = [];
        $taken = [];
        $count = 0;

        foreach (ArtifactReader::entries($snapshot->artifactPath()) as $entry) {
            if ($entry['type'] !== 'device') {
                continue;
            }
            $row = $this->device($snapshot, $entry['id'], $entry['data'], $brands, $levels);
            $devices[] = $row;
            $taken[$entry['id']] = true;
            foreach ($this->spellingPaths($row['brand_slug'], $entry['data']) as $path) {
                $aliases[$path] = $entry['id'];
            }
            $count++;
            if (count($devices) >= self::CHUNK) {
                OdacDevice::query()->insert($devices);
                $devices = [];
            }
        }
        if ($devices !== []) {
            OdacDevice::query()->insert($devices);
        }

        $rows = [];
        foreach ($aliases as $path => $target) {
            // A real device id always wins over a spelling alias.
            if (! isset($taken[$path]) && $path !== $target) {
                $rows[] = ['snapshot_id' => $snapshot->id, 'path' => $path, 'odac_id' => $target];
            }
        }
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            OdacAlias::query()->insert($chunk);
        }

        return $count;
    }

    private function device(OdacSnapshot $snapshot, string $id, array $data, array $brands, array $levels): array
    {
        [$brandSlug, $modelSlug] = explode('/', $id, 2);
        // `data.brand` is the brand entity id (`audio_gd`), which is not always
        // the first segment of the device id (`audio-gd/...`).
        $brandRef = is_string($data['brand'] ?? null) ? $data['brand'] : $brandSlug;
        $brandName = $this->text($brands[$brandRef]['name'] ?? null)
            ?? $this->text($brands[$brandSlug]['name'] ?? null)
            ?? $brandSlug;
        $modelName = $this->text($data['model'] ?? null) ?? $this->text($data['name'] ?? null) ?? $modelSlug;

        $search = [$brandName, $modelName, $brandSlug, $modelSlug];
        foreach (self::aliases($data) as $alias) {
            // A neighbour is ANOTHER product: searching it must not land here.
            if ($alias['relation'] !== 'neighbour') {
                $search[] = $alias['name'];
            }
        }

        // The level is not in the artifact: it is computed from the
        // vocabulary's own field list, never from a stored figure.
        $level = Completeness::level($data, $levels);

        return [
            'snapshot_id' => $snapshot->id,
            'odac_id' => $id,
            'brand_slug' => $brandSlug,
            'model_slug' => $modelSlug,
            'brand_name' => Str::limit($brandName, 190, ''),
            'model_name' => Str::limit($modelName, 190, ''),
            'kind' => (string) $data['kind'],
            'level' => is_int($level) && $level >= 0 && $level <= 3 ? $level : null,
            'identified' => Completeness::identified($data),
            'is_contested' => ! empty($data['contested']),
            'has_open_doubt' => ! empty($data['open_doubt']),
            'superseded_by' => is_string($data['superseded_by'] ?? null)
                && preg_match(ArtifactReader::DEVICE_ID_PATTERN, $data['superseded_by'])
                    ? $data['superseded_by'] : null,
            'search_text' => mb_strtolower(implode(' | ', array_unique($search))),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];
    }

    /** @return list<string> */
    private function spellingPaths(string $brandSlug, array $data): array
    {
        $paths = [];
        foreach (self::aliases($data) as $alias) {
            if ($alias['relation'] !== 'spelling') {
                continue;
            }
            $slug = Str::slug($alias['name']);
            if ($slug !== '') {
                $paths[] = $brandSlug.'/'.$slug;
            }
        }

        return $paths;
    }

    /**
     * Aliases are `{"relation", "text"}` and typed: `spelling` (same object written otherwise), `edition`
     * (a variant no fact tells apart yet), `neighbour` (a DIFFERENT product).
     *
     * @return list<array{name: string, relation: string}>
     */
    public static function aliases(array $data): array
    {
        $out = [];
        foreach ((array) ($data['aliases'] ?? []) as $alias) {
            if (is_string($alias)) {
                // Untyped: it may well be a neighbour, read it as one.
                $alias = ['text' => $alias, 'relation' => 'neighbour'];
            }
            $name = is_array($alias) ? ($alias['text'] ?? $alias['name'] ?? null) : null;
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $alias['name'] = $name;
            $relation = in_array($alias['relation'] ?? null, ['spelling', 'edition', 'neighbour'], true)
                ? $alias['relation'] : 'neighbour'; // unknown relation: the cautious reading
            $out[] = ['name' => trim($alias['name']), 'relation' => $relation];
        }

        return $out;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
