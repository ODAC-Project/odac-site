<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Actions\Odac;

use App\Models\OdacDevice;
use App\Models\OdacSnapshot;
use App\Support\Odac\ArtifactReader;
use App\Support\Odac\Completeness;
use Illuminate\Support\Facades\Cache;

/**
 * Every figure the home, statistics and data-health pages show, computed
 * from the current projection and the artifact on disk (never from the
 * network), once per fingerprint: the figures only change with the snapshot.
 */
final class CatalogueStats
{
    private const VERSION = 'v1';

    public function __invoke(?OdacSnapshot $snapshot): array
    {
        if ($snapshot === null) {
            return $this->compute(null);
        }

        try {
            return Cache::rememberForever(
                'odac:stats:'.self::VERSION.':'.$snapshot->fingerprint,
                fn () => $this->compute($snapshot),
            );
        } catch (\Throwable $e) {
            // A cache that cannot be written must not take the pages down:
            // the figures are recomputed, which only costs time.
            report($e);

            return $this->compute($snapshot);
        }
    }

    private function compute(?OdacSnapshot $snapshot): array
    {
        [$families, $levels] = $snapshot ? $this->vocabulary($snapshot) : [[], []];

        $devices = 0;
        $brands = [];
        $kinds = [];
        $byLevel = [];
        $byFamily = [];
        $contributors = [];
        $hosts = [];
        $claims = 0;
        $contested = 0;
        $doubts = 0;
        $unproven = 0;
        $rangeOnly = 0;
        $missing = [];
        foreach ([2, 3] as $n) {
            foreach ($levels[$n] ?? [] as $field) {
                $missing[$field] = ['field' => $field, 'level' => $n, 'missing' => 0];
            }
        }

        $query = OdacDevice::query()
            ->select(['brand_slug', 'brand_name', 'kind', 'level', 'is_contested', 'has_open_doubt', 'data'])
            ->whereNull('superseded_by');
        foreach ($query->lazy(100) as $device) {
            $data = $device->data;
            $devices++;
            $brands[$device->brand_slug] ??= ['slug' => $device->brand_slug, 'name' => $device->brand_name, 'devices' => 0, 'level2' => 0];
            $brands[$device->brand_slug]['devices']++;
            if (($device->level ?? 0) >= 2) {
                $brands[$device->brand_slug]['level2']++;
            }
            $kinds[$device->kind] = ($kinds[$device->kind] ?? 0) + 1;
            $levelKey = $device->level === null ? 'none' : (string) $device->level;
            $byLevel[$levelKey] = ($byLevel[$levelKey] ?? 0) + 1;
            $family = is_string($data['family'] ?? null) ? $data['family'] : null;
            $familyKey = $family ?? '';
            $byFamily[$familyKey] = ($byFamily[$familyKey] ?? 0) + 1;
            $contested += $device->is_contested ? 1 : 0;
            $doubts += $device->has_open_doubt ? 1 : 0;

            // One proof = one claim file of this device.
            $proofs = [];
            $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
            foreach ($evidence as $proof) {
                if (! is_array($proof)) {
                    continue;
                }
                $source = is_array($proof['source'] ?? null) ? $proof['source'] : [];
                $key = is_string($proof['claim'] ?? null) ? $proof['claim'] : sha1(json_encode($source));
                $proofs[$key] = [
                    'by' => is_string($proof['submitted_by'] ?? null) ? $proof['submitted_by'] : 'anonymous',
                    'host' => self::host($source['url'] ?? null),
                    'range' => ($proof['names_subject'] ?? $source['names_subject'] ?? null) === false,
                ];
            }
            foreach ($proofs as $proof) {
                $claims++;
                $contributors[$proof['by']] = ($contributors[$proof['by']] ?? 0) + 1;
                if ($proof['host'] !== null) {
                    $hosts[$proof['host']] = ($hosts[$proof['host']] ?? 0) + 1;
                }
                $rangeOnly += $proof['range'] ? 1 : 0;
            }

            // A fact shown without a proof next to it.
            foreach ($data as $field => $value) {
                if (! str_starts_with((string) $field, '_') && ! in_array($field, BuildDeviceSheet::NOT_FACTS, true) && ! isset($evidence[$field])) {
                    $unproven++;
                    break;
                }
            }

            foreach ($missing as $field => $row) {
                if (Completeness::missing($data, [$field]) !== []) {
                    $missing[$field]['missing']++;
                }
            }
        }

        $byBrand = array_values($brands);
        usort($byBrand, fn ($a, $b) => $b['devices'] <=> $a['devices'] ?: strcasecmp($a['name'], $b['name']));
        arsort($kinds);
        arsort($contributors);
        arsort($hosts);
        $missing = array_values($missing);
        usort($missing, fn ($a, $b) => $b['missing'] <=> $a['missing'] ?: $a['level'] <=> $b['level']);

        $familyRows = [];
        foreach ($byFamily as $id => $count) {
            $familyRows[] = [
                'id' => $id === '' ? null : (string) $id,
                'name' => $id === '' ? null : ($families[$id] ?? null),
                'devices' => $count,
            ];
        }
        usort($familyRows, fn ($a, $b) => $b['devices'] <=> $a['devices']);
        $knownFamilies = [];
        foreach ($families as $id => $name) {
            $knownFamilies[] = ['id' => (string) $id, 'name' => $name, 'devices' => $byFamily[$id] ?? 0];
        }
        usort($knownFamilies, fn ($a, $b) => $b['devices'] <=> $a['devices'] ?: strcmp($a['id'], $b['id']));

        $levelRows = [];
        foreach (['3', '2', '1', '0', 'none'] as $key) {
            if (isset($byLevel[$key])) {
                $levelRows[] = ['level' => $key === 'none' ? null : (int) $key, 'devices' => $byLevel[$key]];
            }
        }

        return [
            'totals' => [
                'brands' => count($brands),
                'devices' => $devices,
                'claims' => $claims,
                'families' => count($families),
            ],
            'by_kind' => array_map(fn ($k, $n) => ['kind' => (string) $k, 'devices' => $n], array_keys($kinds), $kinds),
            'by_brand' => $byBrand,
            'by_family' => $familyRows,
            'families' => $knownFamilies,
            'by_level' => $levelRows,
            'levels' => [
                'known' => $levels !== [],
                'level2' => ($byLevel['2'] ?? 0) + ($byLevel['3'] ?? 0),
                'level3' => $byLevel['3'] ?? 0,
                'fields' => array_map(fn ($n) => $levels[$n] ?? [], [2 => 2, 3 => 3]),
            ],
            'contributors' => array_map(fn ($k, $n) => ['name' => (string) $k, 'claims' => $n], array_keys($contributors), $contributors),
            'sources' => array_map(fn ($k, $n) => ['host' => (string) $k, 'claims' => $n], array_keys($hosts), $hosts),
            'health' => [
                'contested' => $contested,
                'open_doubts' => $doubts,
                'unproven' => $unproven,
                'range_only' => $rangeOnly,
                'missing' => $missing,
            ],
        ];
    }

    /** Chip family names and completeness levels, read from the local artifact. */
    private function vocabulary(OdacSnapshot $snapshot): array
    {
        $families = [];
        $levels = [];
        $path = $snapshot->artifactPath();
        if (! is_file($path)) {
            return [[], []];
        }
        foreach (ArtifactReader::entries($path) as $entry) {
            if ($entry['type'] === 'chip_family') {
                $name = is_array($entry['data']) ? ($entry['data']['name'] ?? null) : null;
                $families[$entry['id']] = is_string($name) ? $name : $entry['id'];
            } elseif ($entry['type'] === 'vocabulary' && $entry['id'] === 'completeness') {
                $levels = ArtifactReader::levels($entry['data']);
            }
        }

        return [$families, $levels];
    }

    private static function host(mixed $url): ?string
    {
        if (! is_string($url) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === '' ? null : preg_replace('/^www\./', '', $host);
    }
}
