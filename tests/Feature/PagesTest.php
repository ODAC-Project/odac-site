<?php

// SPDX-License-Identifier: Apache-2.0

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();
});

it('computes the completeness level from the vocabulary, not from a stored figure', function () {
    $levels = \App\Models\OdacDevice::query()->pluck('level', 'odac_id')->all();

    // convertisseur-un carries every level-2 field, not the level-3 ones.
    expect($levels['atelier-demo/convertisseur-un'])->toBe(2)
        ->and($levels['atelier-demo/lecteur-reseau'])->toBe(0)
        ->and($levels['banc-essai/modele-a2'])->toBe(0);
});

it('leaves the level unknown when the artifact has no completeness vocabulary', function () {
    $lines = preg_replace('/^.*"id":"completeness".*\n/m', '', odac_demo_jsonl());
    odac_publish($this->dist['dir'], $lines, '2026.11.27');
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();

    expect(\App\Models\OdacDevice::query()->whereNotNull('level')->count())->toBe(0);
    $this->get('/health')->assertInertia(fn (Assert $page) => $page->where('levels.known', false));
});

it('shows the home page with the overview, contributors and sources', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('totals.brands', 2)
            ->where('totals.devices', 3)
            // 3 claims on convertisseur-un, 1 on the player, 1 on modele-a2.
            ->where('totals.claims', 5)
            ->where('totals.families', 1)
            ->where('contributors.0', ['name' => 'curator', 'claims' => 5])
            ->where('sources.0.host', 'example.org')
            ->where('snapshot.date', '2026-11-26'));
});

it('answers 304 on a page whose ETag matches, and changes it with the language', function () {
    $etag = $this->get('/stats', ['Accept-Language' => 'fr'])->assertOk()->headers->get('ETag');
    expect($etag)->not->toBeEmpty();

    $this->get('/stats', ['Accept-Language' => 'fr', 'If-None-Match' => $etag])->assertStatus(304);
    $this->get('/stats', ['Accept-Language' => 'en', 'If-None-Match' => $etag])->assertOk();
});

it('lists brands with their device counts', function () {
    $this->get('/brands')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Brands')
        ->count('brands', 2)
        ->where('brands.0', ['slug' => 'atelier-demo', 'name' => 'Atelier Démo', 'devices' => 2, 'level2' => 1]));
});

it('lists chip families, and the devices without one', function () {
    $this->get('/chips')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Chips')
        ->where('families.0', ['id' => 'delta_sigma_generic', 'name' => 'Delta-sigma (générique)', 'devices' => 1])
        ->where('unassigned.0', ['id' => null, 'name' => null, 'devices' => 2]));
});

it('breaks the registry down by type, level, brand and family', function () {
    $this->get('/stats')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Stats')
        ->where('by_kind', [['kind' => 'dac', 'devices' => 2], ['kind' => 'network_player', 'devices' => 1]])
        ->where('by_level', [['level' => 2, 'devices' => 1], ['level' => 0, 'devices' => 2]])
        ->where('by_brand.0.slug', 'atelier-demo')
        ->where('families.delta_sigma_generic', 'Delta-sigma (générique)'));
});

it('shows data health: levels 2 and 3, what is missing most, and the signals', function () {
    $this->get('/health')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Health')
        ->where('levels.known', true)
        ->where('levels.level2', 1)
        ->where('levels.level3', 0)
        ->where('health.contested', 1)
        ->where('health.open_doubts', 1)
        // native_dsd and dop are written without evidence on convertisseur-un.
        ->where('health.unproven', 1)
        ->where('health.range_only', 1)
        ->where('health.missing', function ($missing) {
            $rows = collect($missing)->keyBy('field');

            return $rows['dsd_reconverted_to_pcm']['missing'] === 3
                && $rows['dsd_reconverted_to_pcm']['level'] === 3
                && $rows['pcm_max_hz']['missing'] === 1
                && $rows['inputs']['missing'] === 2
                && collect($missing)->pluck('missing')->all() === collect($missing)->pluck('missing')->sortDesc()->values()->all();
        }));
});

it('compares two to four devices side by side, ignoring unknown and replaced ones', function () {
    $this->get('/compare?d[]=atelier-demo/convertisseur-un&d[]=atelier-demo/lecteur-reseau&d[]=banc-essai/modele-a&d[]=nope/nope')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Compare')
            ->count('sheets', 2)
            ->where('sheets.0.identity.odac_id', 'atelier-demo/convertisseur-un')
            ->where('sheets.1.identity.odac_id', 'atelier-demo/lecteur-reseau')
            ->where('max', 4)
            ->count('options', 3));

    $this->get('/compare')->assertOk()->assertInertia(fn (Assert $page) => $page->count('sheets', 0));
});

it('keeps at most four devices in a comparison', function () {
    $lines = odac_demo_jsonl();
    foreach (range(1, 5) as $i) {
        $lines .= odac_device_line("serie/m{$i}")."\n";
    }
    odac_publish($this->dist['dir'], $lines, '2026.11.27');
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();

    $query = implode('&', array_map(fn ($i) => "d[]=serie/m{$i}", range(1, 5)));
    $this->get('/compare?'.$query)->assertInertia(fn (Assert $page) => $page->count('sheets', 4));
});

it('gives a sheet the family names and what it still lacks for the next level', function () {
    $this->get('/atelier-demo/convertisseur-un')->assertInertia(fn (Assert $page) => $page
        ->where('family_names.delta_sigma_generic', 'Delta-sigma (générique)')
        ->where('completeness.level2_missing', [])
        ->where('completeness.level3_missing', ['dsd_reconverted_to_pcm', 'pcm_dsd_level_offset_db', 'bits', 'analog_filter', 'internal_bypass']));
});

it('serves the contribute and help pages without any form', function (string $path, string $component) {
    $response = $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    expect($response->getContent())->not->toContain('<form');
})->with([
    ['/contribute', 'Contribute'],
    ['/help', 'Help'],
]);

it('renders every page even before the first ingestion', function (string $path) {
    \App\Models\OdacSnapshot::query()->update(['is_current' => false]);
    \App\Models\OdacDevice::query()->delete();

    $this->get($path)->assertOk();
})->with(['/', '/devices', '/brands', '/chips', '/stats', '/health', '/compare', '/contribute', '/help']);
