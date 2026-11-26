<?php

// SPDX-License-Identifier: Apache-2.0

use App\Support\Odac\IngestionFailed;
use App\Support\Odac\Pointer;

it('reads the 135-byte pointer published by the CI', function () {
    $p = Pointer::fromJson('{ "version": "2026.11.25", "fingerprint": "adaa3dab07d58ae1", "url": "odac-2026.11.25-adaa3dab07d58ae1.jsonl", "entries": 704 }');

    expect($p->filename)->toBe('odac-2026.11.25-adaa3dab07d58ae1.jsonl')->and($p->entries)->toBe(704);
});

it('refuses a pointer that does not hold together', function (array $pointer) {
    Pointer::fromJson(json_encode($pointer));
})->with([
    'uppercase fingerprint' => [['version' => '2026.11.25', 'fingerprint' => 'ADAA3DAB07D58AE1', 'url' => 'odac-2026.11.25-ADAA3DAB07D58AE1.jsonl', 'entries' => 1]],
    'url without fingerprint' => [['version' => '2026.11.25', 'fingerprint' => 'adaa3dab07d58ae1', 'url' => 'odac-2026.11.25.jsonl', 'entries' => 1]],
    'url elsewhere' => [['version' => '2026.11.25', 'fingerprint' => 'adaa3dab07d58ae1', 'url' => 'https://evil.example/odac-2026.11.25-adaa3dab07d58ae1.jsonl', 'entries' => 1]],
    'version with slash' => [['version' => '2026/11', 'fingerprint' => 'adaa3dab07d58ae1', 'url' => 'odac-2026/11-adaa3dab07d58ae1.jsonl', 'entries' => 1]],
    'entries as string' => [['version' => '2026.11.25', 'fingerprint' => 'adaa3dab07d58ae1', 'url' => 'odac-2026.11.25-adaa3dab07d58ae1.jsonl', 'entries' => '704']],
    'nothing' => [[]],
])->throws(IngestionFailed::class);
