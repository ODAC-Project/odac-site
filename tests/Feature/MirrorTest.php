<?php

// SPDX-License-Identifier: Apache-2.0

use App\Models\OdacSnapshot;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();
});

it('serves latest.json from stored values, with the fingerprint as ETag and an exact Cache-Control', function () {
    $response = $this->get('/latest.json');

    $response->assertOk()
        ->assertHeader('ETag', '"'.$this->dist['fingerprint'].'"')
        ->assertHeader('Cache-Control', 'max-age=300, must-revalidate, public')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertExactJson([
            'version' => $this->dist['version'],
            'fingerprint' => $this->dist['fingerprint'],
            'url' => 'artifacts/'.$this->dist['filename'],
            'entries' => $this->dist['entries'],
        ]);
    expect($response->headers->getCookies())->toBe([]);
});

it('answers If-None-Match on latest.json with a bodiless 304 after a single query', function () {
    DB::enableQueryLog();
    $response = $this->get('/latest.json', ['If-None-Match' => '"'.$this->dist['fingerprint'].'"']);

    $response->assertStatus(304)->assertHeader('ETag', '"'.$this->dist['fingerprint'].'"');
    expect($response->getContent())->toBe('')
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('accepts a weak validator, as nginx gzip would make it', function () {
    $this->get('/latest.json', ['If-None-Match' => 'W/"'.$this->dist['fingerprint'].'"'])->assertStatus(304);
});

it('answers 200 when the client holds an older fingerprint', function () {
    $this->get('/latest.json', ['If-None-Match' => '"0000000000000000"'])->assertOk();
});

it('serves the artifact compressed, and checks the bytes actually sent', function () {
    $response = $this->get('/artifacts/'.$this->dist['filename'], ['Accept-Encoding' => 'br, gzip;q=0.8']);

    $response->assertOk()
        ->assertHeader('Content-Encoding', 'gzip')
        ->assertHeader('Content-Type', 'application/x-ndjson; charset=utf-8')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('ETag', '"'.$this->dist['fingerprint'].'-gz"');
    expect($response->headers->get('Vary'))->toContain('Accept-Encoding');

    $sent = odac_sent_bytes($response);
    expect(substr($sent, 0, 2))->toBe("\x1f\x8b")           // gzip magic number
        ->and(gzdecode($sent))->toBe(odac_demo_jsonl())
        ->and(strlen($sent))->toBeLessThan(strlen(odac_demo_jsonl()) / 2);
});

it('serves the raw artifact to a client that does not take gzip', function (string $acceptEncoding) {
    $response = $this->get('/artifacts/'.$this->dist['filename'], ['Accept-Encoding' => $acceptEncoding]);

    $response->assertOk()->assertHeaderMissing('Content-Encoding')
        ->assertHeader('ETag', '"'.$this->dist['fingerprint'].'"');
    expect(odac_sent_bytes($response))->toBe(odac_demo_jsonl());
})->with(['identity', 'gzip;q=0', 'br']);

it('answers If-None-Match on an artifact with a 304 without touching the file', function () {
    unlink(OdacSnapshot::current()->gzipPath()); // proves the disk is not read

    $this->get('/artifacts/'.$this->dist['filename'], [
        'Accept-Encoding' => 'gzip',
        'If-None-Match' => '"'.$this->dist['fingerprint'].'-gz"',
    ])->assertStatus(304);
});

it('keeps serving an older artifact after a new fingerprint', function () {
    $next = odac_publish($this->dist['dir'], odac_demo_jsonl().odac_device_line('banc-essai/modele-b')."\n", '2026.11.27');
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();

    $this->get('/latest.json')->assertJsonPath('fingerprint', $next['fingerprint']);
    $this->get('/artifacts/'.$this->dist['filename'], ['Accept-Encoding' => 'identity'])->assertOk();
    $this->get('/artifacts/'.$next['filename'], ['Accept-Encoding' => 'identity'])->assertOk();
});

it('returns 404 for unknown or malformed artifact names', function (string $name) {
    $this->get('/artifacts/'.$name)->assertNotFound();
})->with([
    'odac-2026.11.26-ffffffffffffffff.jsonl',
    'latest.json',
    '..%2F..%2F.env',
    'odac-2026.11.26-ABCDEF0123456789.jsonl',
]);

it('says 404 with no-store before any ingestion', function () {
    OdacSnapshot::query()->delete();

    $this->get('/latest.json')->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
});

it('accepts gzip according to q-values', function (string $header, bool $expected) {
    expect(\App\Http\Controllers\MirrorController::acceptsGzip($header))->toBe($expected);
})->with([
    ['gzip, deflate, br', true],
    ['GZIP', true],
    ['gzip;q=0', false],
    ['gzip; q=0.0', false],
    ['*', true],
    ['*;q=0', false],
    ['br, *;q=0.1', true],
    ['identity', false],
    ['', false],
]);
