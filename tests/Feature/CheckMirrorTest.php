<?php

// SPDX-License-Identifier: Apache-2.0

use Illuminate\Support\Facades\Http;

it('passes against a mirror that compresses for real', function () {
    $raw = str_repeat(odac_demo_jsonl(), 20);
    Http::fake(function ($request) use ($raw) {
        if (str_ends_with($request->url(), '/latest.json')) {
            return $request->hasHeader('If-None-Match')
                ? Http::response('', 304, ['ETag' => '"abc"'])
                : Http::response(['url' => 'artifacts/odac-1-abcdefabcdefabcd.jsonl'], 200, ['ETag' => '"abc"', 'Cache-Control' => 'max-age=300, public']);
        }

        return Http::response(gzencode($raw, 9), 200, [
            'Content-Encoding' => 'gzip', 'Cache-Control' => 'immutable, max-age=31536000, public', 'Vary' => 'Accept-Encoding',
        ]);
    });

    $this->artisan('odac:check-mirror', ['base' => 'https://mirror.example.org'])->assertSuccessful();
});

it('fails against a mirror that sends the artifact uncompressed', function () {
    Http::fake(function ($request) {
        if (str_ends_with($request->url(), '/latest.json')) {
            return Http::response(['url' => 'artifacts/x.jsonl'], $request->hasHeader('If-None-Match') ? 304 : 200, ['ETag' => '"abc"', 'Cache-Control' => 'max-age=300']);
        }

        return Http::response(str_repeat(odac_demo_jsonl(), 20), 200, ['Cache-Control' => 'immutable']);
    });

    $this->artisan('odac:check-mirror', ['base' => 'https://mirror.example.org'])
        ->expectsOutputToContain('FAIL artifact is sent gzip-encoded')
        ->assertFailed();
});
