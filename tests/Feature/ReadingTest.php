<?php

// SPDX-License-Identifier: Apache-2.0

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();
});

it('lists devices, hiding replaced sheets, with a public Cache-Control and no cookie', function () {
    $response = $this->get('/devices');

    $response->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Index')
            ->where('devices.total', 3)
            ->where('devices.data.0.odac_id', 'atelier-demo/convertisseur-un')
            ->where('snapshot.fingerprint', $this->dist['fingerprint']));
    expect($response->headers->getCookies())->toBe([]);
    expect($response->headers->get('Vary'))->toBe('X-Inertia, Accept-Language, Cookie');
});

it('keeps the old list addresses: /?q=… answers a 301 to /devices', function () {
    $this->get('/?q=lecteur&kind=network_player')
        ->assertStatus(301)
        ->assertRedirect('/devices?q=lecteur&kind=network_player');
    $this->get('/?page=2')->assertStatus(301)->assertRedirect('/devices?page=2');
});

it('filters by search (name and alias), brand, kind, contested and doubt', function (array $query, array $ids) {
    $this->get('/devices?'.http_build_query($query))
        ->assertInertia(fn (Assert $page) => $page
            ->where('devices.total', count($ids))
            ->where('devices.data', fn ($data) => collect($data)->pluck('odac_id')->all() === $ids));
})->with([
    'model name' => [['q' => 'lecteur'], ['atelier-demo/lecteur-reseau']],
    'spelling alias' => [['q' => 'Convertisseur 1'], ['atelier-demo/convertisseur-un']],
    'neighbour alias does not match' => [['q' => 'Convertisseur Deux'], []],
    'LIKE wildcards are literal' => [['q' => '%'], []],
    'brand' => [['brand' => 'banc-essai'], ['banc-essai/modele-a2']],
    'kind' => [['kind' => 'network_player'], ['atelier-demo/lecteur-reseau']],
    'contested' => [['contested' => 1], ['atelier-demo/convertisseur-un']],
    'open doubt' => [['doubt' => 1], ['atelier-demo/convertisseur-un']],
    'level 2 and above' => [['level' => 2], ['atelier-demo/convertisseur-un']],
    'level 3 and above' => [['level' => 3], []],
    'chip family' => [['family' => 'delta_sigma_generic'], ['atelier-demo/convertisseur-un']],
]);

it('paginates by 48 and keeps the filters in the links', function () {
    $lines = odac_demo_jsonl();
    for ($i = 1; $i <= 60; $i++) {
        $lines .= odac_device_line(sprintf('serie/m%02d', $i), ['model' => sprintf('M%02d', $i)])."\n";
    }
    odac_publish($this->dist['dir'], $lines, '2026.11.27');
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();

    $this->get('/devices?brand=serie&page=2')->assertInertia(fn (Assert $page) => $page
        ->where('devices.total', 60)
        ->where('devices.per_page', 48)
        ->where('devices.current_page', 2)
        ->count('devices.data', 12)
        ->where('devices.prev_page_url', fn ($url) => str_contains($url, 'brand=serie')));
});

it('shows a sheet grouped by proof, each quote once, with rival values', function () {
    $this->get('/atelier-demo/convertisseur-un')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Show')
            ->where('sheet.identity.brand', 'Atelier Démo')
            ->where('sheet.identity.model', 'Convertisseur Un')
            ->count('sheet.proofs', 3)
            ->where('sheet.proofs.0.claim', '001-fiche-technique.json')
            ->where('sheet.proofs.0.source.type', 'datasheet')
            ->count('sheet.proofs.0.fields', 4)
            ->where('sheet.proofs.2.source.names_subject', false)
            ->where('sheet.summary', function ($summary) {
                $rows = collect($summary)->keyBy('field');

                return $rows['dsd_max']['proof'] === 1
                    && count($rows['dsd_max']['contested']) === 2
                    && $rows['dsd_max']['contested'][0]['chosen'] === true
                    && $rows['dsd_max']['contested'][1]['value'] === 'none'
                    && $rows['dsd_direct']['value'] === 'to_be_measured'
                    && $rows['dsd_direct']['proof'] === null
                    && $rows['inputs.spdif_coax.pcm_max_hz']['proof'] === 2
                    && ! $rows->has('_why') && ! $rows->has('usb') && ! $rows->has('brand')
                    && $rows['device_settings']['value'][0]['set_to'] === 'lent';
            })
            ->where('sheet.identity.usb_ids.0.pid', '0x0001')
            ->where('sheet.open_doubt.raised_on', '2026-11-22')
            ->where('sheet.open_doubt.text', fn ($t) => str_contains($t, 'mesurer')));
});

it('sets an ETag from stored values on a sheet and answers 304', function () {
    $etag = $this->get('/atelier-demo/convertisseur-un')->assertOk()->headers->get('ETag');
    expect($etag)->not->toBeEmpty();

    $this->get('/atelier-demo/convertisseur-un', ['If-None-Match' => $etag])->assertStatus(304);

    // A new snapshot changes the ETag.
    odac_publish($this->dist['dir'], odac_demo_jsonl().odac_device_line('banc-essai/modele-b')."\n", '2026.11.27');
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();
    $this->get('/atelier-demo/convertisseur-un', ['If-None-Match' => $etag])->assertOk();
});

it('redirects a spelling alias and a replaced sheet with a 301', function () {
    $this->get('/atelier-demo/convertisseur-1')->assertStatus(301)->assertRedirect('/atelier-demo/convertisseur-un');
    $this->get('/banc-essai/modele-a')->assertStatus(301)->assertRedirect('/banc-essai/modele-a2');
});

it('does not answer for a neighbour alias or an unknown device', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/atelier-demo/convertisseur-deux', '/atelier-demo/inconnu', '/Atelier-Demo/convertisseur-un']);

it('never lets a sheet route shadow the mirror', function () {
    $this->get('/artifacts/'.$this->dist['filename'], ['Accept-Encoding' => 'identity'])
        ->assertOk()->assertHeader('Content-Type', 'application/x-ndjson; charset=utf-8');
    $this->get('/artifacts/nimporte')->assertNotFound();
});
