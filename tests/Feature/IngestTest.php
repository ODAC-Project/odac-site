<?php

// SPDX-License-Identifier: Apache-2.0

use App\Models\OdacAlias;
use App\Models\OdacDevice;
use App\Models\OdacSnapshot;
use Illuminate\Support\Facades\Http;

it('ingests the artifact, verifies it and keeps it on disk with its gzip twin', function () {
    $dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());

    $this->artisan('odac:ingest', ['--source' => $dist['dir']])
        ->expectsOutputToContain('ingested: '.$dist['filename'])
        ->assertSuccessful();

    $snapshot = OdacSnapshot::current();
    expect($snapshot->fingerprint)->toBe($dist['fingerprint'])
        ->and($snapshot->entries)->toBe($dist['entries'])
        ->and(is_file($snapshot->artifactPath()))->toBeTrue()
        ->and(gzdecode(file_get_contents($snapshot->gzipPath())))->toBe(odac_demo_jsonl());

    expect(OdacDevice::count())->toBe(4)
        ->and(OdacDevice::where('odac_id', 'atelier-demo/convertisseur-un')->value('brand_name'))->toBe('Atelier Démo')
        ->and(OdacDevice::where('odac_id', 'atelier-demo/convertisseur-un')->value('is_contested'))->toBeTruthy();
});

it('does not download anything when the fingerprint is unchanged', function () {
    $dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $dist['dir']])->assertSuccessful();

    // The artifact disappears upstream: an unchanged pointer must not need it.
    unlink($dist['dir'].'/'.$dist['filename']);

    $this->artisan('odac:ingest', ['--source' => $dist['dir']])
        ->expectsOutputToContain('unchanged')
        ->assertSuccessful();
    expect(OdacSnapshot::count())->toBe(1);
});

it('reads latest.json over HTTP and fetches the artifact only on a new fingerprint', function () {
    $jsonl = odac_demo_jsonl();
    $fingerprint = substr(hash('sha256', $jsonl), 0, 16);
    $name = "odac-2026.11.26-{$fingerprint}.jsonl";
    Http::fake([
        'https://dist.example.org/latest.json' => Http::response(['version' => '2026.11.26', 'fingerprint' => $fingerprint, 'url' => $name, 'entries' => 10]),
        "https://dist.example.org/{$name}" => Http::response($jsonl),
    ]);

    $this->artisan('odac:ingest', ['--source' => 'https://dist.example.org'])->assertSuccessful();
    $this->artisan('odac:ingest', ['--source' => 'https://dist.example.org'])->expectsOutputToContain('unchanged');

    Http::assertSentCount(3); // pointer, artifact, pointer
    expect(OdacSnapshot::current()->fingerprint)->toBe($fingerprint);
});

it('refuses an artifact whose bytes do not match the announced fingerprint, and keeps serving the old one', function () {
    $dir = odac_tmp_dir('dist');
    $good = odac_publish($dir, odac_demo_jsonl(), '2026.11.25');
    $this->artisan('odac:ingest', ['--source' => $dir])->assertSuccessful();

    $next = odac_publish($dir, odac_demo_jsonl().odac_device_line('banc-essai/modele-b')."\n", '2026.11.26');
    file_put_contents($dir.'/'.$next['filename'], odac_demo_jsonl().odac_device_line('banc-essai/autre')."\n");

    $this->artisan('odac:ingest', ['--source' => $dir])
        ->expectsOutputToContain('Fingerprint mismatch')
        ->assertFailed();

    expect(OdacSnapshot::current()->fingerprint)->toBe($good['fingerprint'])
        ->and(OdacDevice::count())->toBe(4)
        ->and(glob(storage_path('app/odac/artifacts/.incoming-*')))->toBe([]);
});

it('refuses a pointer whose url does not carry the fingerprint', function () {
    $dir = odac_tmp_dir('dist');
    $dist = odac_publish($dir, odac_demo_jsonl());
    file_put_contents("{$dir}/latest.json", json_encode([
        'version' => $dist['version'], 'fingerprint' => $dist['fingerprint'], 'url' => '../../etc/passwd', 'entries' => $dist['entries'],
    ]));

    $this->artisan('odac:ingest', ['--source' => $dir])->expectsOutputToContain('"url" must be')->assertFailed();
    expect(OdacSnapshot::count())->toBe(0);
});

it('refuses an artifact whose entry count differs from the pointer', function () {
    $dir = odac_tmp_dir('dist');
    $dist = odac_publish($dir, odac_demo_jsonl());
    $pointer = json_decode(file_get_contents("{$dir}/latest.json"), true);
    $pointer['entries']++;
    file_put_contents("{$dir}/latest.json", json_encode($pointer));

    $this->artisan('odac:ingest', ['--source' => $dir])->expectsOutputToContain('entries')->assertFailed();
    expect(OdacSnapshot::count())->toBe(0);
});

it('refuses malformed lines and device ids that are not brand/model', function (string $line) {
    $dir = odac_tmp_dir('dist');
    odac_publish($dir, odac_demo_jsonl().$line."\n");

    $this->artisan('odac:ingest', ['--source' => $dir])->assertFailed();
    expect(OdacSnapshot::count())->toBe(0);
})->with([
    'not json' => ['{"type": "device",'],
    'unknown type' => ['{"type":"amplifier","id":"x/y","data":{}}'],
    'one-segment device id' => ['{"type":"device","id":"seul","data":{"kind":"dac"}}'],
    'path traversal id' => ['{"type":"device","id":"../x","data":{"kind":"dac"}}'],
    'device without kind' => ['{"type":"device","id":"a/b","data":{}}'],
    'device as a string' => ['{"type":"device","id":"a/b","data":"texte"}'],
    'duplicate' => [odac_device_line('banc-essai/modele-a2')],
]);

it('keeps old artifacts and switches the projection to the new fingerprint', function () {
    $dir = odac_tmp_dir('dist');
    $old = odac_publish($dir, odac_demo_jsonl(), '2026.11.25');
    $this->artisan('odac:ingest', ['--source' => $dir])->assertSuccessful();

    $new = odac_publish($dir, odac_demo_jsonl().odac_device_line('banc-essai/modele-b', ['model' => 'Modèle B'])."\n", '2026.11.26');
    $this->artisan('odac:ingest', ['--source' => $dir])->expectsOutputToContain('ingested')->assertSuccessful();

    expect(OdacSnapshot::count())->toBe(2)
        ->and(OdacSnapshot::current()->fingerprint)->toBe($new['fingerprint'])
        ->and(OdacSnapshot::where('is_current', true)->count())->toBe(1)
        ->and(is_file(storage_path('app/odac/artifacts/'.$old['filename'])))->toBeTrue()
        ->and(OdacDevice::count())->toBe(5)
        ->and(OdacDevice::distinct()->pluck('snapshot_id')->all())->toBe([OdacSnapshot::current()->id]);

    // Upstream rolls back: the old artifact is reactivated without a download.
    odac_publish($dir, odac_demo_jsonl(), '2026.11.25');
    unlink($dir.'/'.$old['filename']);
    $this->artisan('odac:ingest', ['--source' => $dir])->expectsOutputToContain('reactivated')->assertSuccessful();
    expect(OdacSnapshot::current()->fingerprint)->toBe($old['fingerprint'])->and(OdacDevice::count())->toBe(4);
});

it('stores spelling aliases as redirects, never neighbours', function () {
    $dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $dist['dir']])->assertSuccessful();

    expect(OdacAlias::pluck('odac_id', 'path')->all())
        ->toBe(['atelier-demo/convertisseur-1' => 'atelier-demo/convertisseur-un']);

    $search = OdacDevice::where('odac_id', 'atelier-demo/convertisseur-un')->value('search_text');
    expect($search)->toContain('convertisseur un se')->not->toContain('convertisseur deux');
});

it('takes the URL of latest.json itself and resolves the artifact next to it', function () {
    $jsonl = odac_demo_jsonl();
    $fingerprint = substr(hash('sha256', $jsonl), 0, 16);
    $name = "odac-2026.11.26-{$fingerprint}.jsonl";
    $pointer = json_encode(['version' => '2026.11.26', 'fingerprint' => $fingerprint, 'url' => $name, 'entries' => 10], JSON_PRETTY_PRINT);
    Http::fake([
        'https://raw.example.org/org/repo/main/dist/latest.json' => Http::response($pointer),
        "https://raw.example.org/org/repo/main/dist/{$name}" => Http::response($jsonl),
    ]);

    $this->artisan('odac:ingest', ['--source' => 'https://raw.example.org/org/repo/main/dist/latest.json'])->assertSuccessful();
    expect(OdacSnapshot::current()->fingerprint)->toBe($fingerprint);
});

it('reads the brand name through data.brand, which may differ from the id prefix', function () {
    $dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $dist['dir'].'/latest.json'])->assertSuccessful();

    expect(OdacDevice::where('odac_id', 'banc-essai/modele-a2')->value('brand_name'))->toBe("Banc d'Essai");
});

it('accepts a vocabulary entry written as plain prose', function () {
    $dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl().'{"data":"Texte de démonstration.","id":"purpose","type":"vocabulary"}'."\n");
    $this->artisan('odac:ingest', ['--source' => $dist['dir']])->assertSuccessful();
});

it('fails cleanly without a configured source', function () {
    config(['odac.source' => '']);
    $this->artisan('odac:ingest')->expectsOutputToContain('ODAC_SOURCE')->assertFailed();
});
