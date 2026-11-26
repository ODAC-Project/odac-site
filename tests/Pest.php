<?php

// SPDX-License-Identifier: Apache-2.0

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/** The neutral demo artifact (invented brands, invented quotes). */
function odac_demo_jsonl(): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/odac/demo.jsonl');
}

/** A fresh, empty directory under the test storage. */
function odac_tmp_dir(string $name): string
{
    $dir = storage_path('framework/testing/'.$name.'-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($dir);

    return $dir;
}

/**
 * Publishes $jsonl as a CI `dist/` would: the artifact named after its
 * fingerprint, and latest.json pointing to it.
 *
 * @return array{dir: string, version: string, fingerprint: string, filename: string, entries: int}
 */
function odac_publish(string $dir, string $jsonl, string $version = '2026.11.26'): array
{
    $fingerprint = substr(hash('sha256', $jsonl), 0, 16);
    $filename = "odac-{$version}-{$fingerprint}.jsonl";
    file_put_contents("{$dir}/{$filename}", $jsonl);
    $entries = count(array_filter(explode("\n", $jsonl), fn ($l) => trim($l) !== ''));
    file_put_contents("{$dir}/latest.json", json_encode([
        'version' => $version, 'fingerprint' => $fingerprint, 'url' => $filename, 'entries' => $entries,
    ]));

    return compact('dir', 'version', 'fingerprint', 'filename', 'entries');
}

/** A device line, for building larger artifacts in tests. */
function odac_device_line(string $id, array $data = []): string
{
    return json_encode(['type' => 'device', 'id' => $id, 'data' => $data + ['model' => $id, 'kind' => 'dac']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** Bytes a response really puts on the wire (BinaryFileResponse included). */
function odac_sent_bytes(\Illuminate\Testing\TestResponse $response): string
{
    ob_start();
    $response->baseResponse->sendContent();

    return (string) ob_get_clean();
}
