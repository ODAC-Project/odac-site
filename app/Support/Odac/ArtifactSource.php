<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

use Illuminate\Support\Facades\Http;

/**
 * Reads the distribution published by the ODAC CI: an http(s) base URL (the
 * repository's `dist/`) or a local directory (tests, offline runs).
 */
final class ArtifactSource
{
    public function __construct(
        private readonly string $base,
        private readonly int $timeout = 30,
        private readonly int $maxBytes = 64 * 1024 * 1024,
    ) {
        if (trim($base) === '') {
            throw new IngestionFailed('No ODAC source configured (ODAC_SOURCE).');
        }
    }

    public function isRemote(): bool
    {
        return (bool) preg_match('#^https?://#i', $this->base);
    }

    public function describe(string $name): string
    {
        return $this->isRemote()
            ? rtrim($this->base, '/').'/'.$name
            : rtrim($this->base, '/').DIRECTORY_SEPARATOR.$name;
    }

    public function pointer(): Pointer
    {
        if ($this->isRemote()) {
            $response = Http::timeout($this->timeout)
                ->accept('application/json')
                ->get($this->describe('latest.json'));
            if (! $response->successful()) {
                throw new IngestionFailed('latest.json: HTTP '.$response->status());
            }
            $body = $response->body();
        } else {
            $body = @file_get_contents($this->describe('latest.json'));
            if ($body === false) {
                throw new IngestionFailed('latest.json not found in '.$this->base);
            }
        }
        if (strlen($body) > 4096) {
            throw new IngestionFailed('latest.json is suspiciously large.');
        }

        return Pointer::fromJson($body);
    }

    /** Copies the artifact into $target and returns its size in bytes. */
    public function download(string $name, string $target): int
    {
        if ($this->isRemote()) {
            $response = Http::timeout($this->timeout)
                ->withOptions(['sink' => $target])
                ->get($this->describe($name));
            if (! $response->successful()) {
                @unlink($target);
                throw new IngestionFailed("{$name}: HTTP ".$response->status());
            }
        } else {
            $path = $this->describe($name);
            if (! is_file($path) || ! @copy($path, $target)) {
                throw new IngestionFailed("{$name} not found in {$this->base}");
            }
        }

        clearstatcache(true, $target);
        $size = (int) filesize($target);
        if ($size > $this->maxBytes) {
            @unlink($target);
            throw new IngestionFailed("{$name} is larger than the configured limit ({$this->maxBytes} bytes).");
        }

        return $size;
    }
}
