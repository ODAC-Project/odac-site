<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Checks a running mirror FROM OUTSIDE, on the bytes really sent: the
 * compression of the artifact (not the nginx setting), the ETag and the 304,
 * the Cache-Control headers. To run after each deployment, and from the
 * outside of the server (through nginx, TLS, any proxy).
 */
class OdacCheckMirror extends Command
{
    protected $signature = 'odac:check-mirror {base : e.g. https://odac.tune.audio}';

    protected $description = 'Checks compression, ETag/304 and Cache-Control on a running ODAC mirror';

    public function handle(): int
    {
        $base = rtrim((string) $this->argument('base'), '/');
        $ok = true;
        $http = fn () => Http::timeout(60)->withOptions(['decode_content' => false]);

        $pointer = $http()->get("{$base}/latest.json");
        if (! $pointer->successful()) {
            $this->error("latest.json: HTTP {$pointer->status()}");

            return self::FAILURE;
        }
        $data = json_decode($this->decoded($pointer->body(), $pointer->header('Content-Encoding')), true);
        $etag = $pointer->header('ETag');
        $ok = $this->check('latest.json carries an ETag', $etag !== '') && $ok;
        $ok = $this->check('latest.json Cache-Control: '.$pointer->header('Cache-Control'), str_contains($pointer->header('Cache-Control'), 'max-age=')) && $ok;

        $revalidated = $http()->withHeaders(['If-None-Match' => $etag])->get("{$base}/latest.json");
        $ok = $this->check('latest.json answers 304 to its own ETag (got '.$revalidated->status().')', $revalidated->status() === 304) && $ok;

        $url = $base.'/'.ltrim((string) ($data['url'] ?? ''), '/');
        $gzip = $http()->withHeaders(['Accept-Encoding' => 'gzip'])->get($url);
        $sent = strlen($gzip->body());
        $raw = strlen($this->decoded($gzip->body(), $gzip->header('Content-Encoding')));
        $this->line(sprintf('  artifact: %d bytes sent, %d decoded (%.1f %% saved)', $sent, $raw, $raw > 0 ? 100 * (1 - $sent / $raw) : 0));
        $ok = $this->check('artifact is sent gzip-encoded', $gzip->header('Content-Encoding') === 'gzip' && str_starts_with($gzip->body(), "\x1f\x8b")) && $ok;
        $ok = $this->check('compressed bytes are fewer than half the raw ones', $raw > 0 && $sent * 2 < $raw) && $ok;
        $ok = $this->check('artifact is immutable', str_contains($gzip->header('Cache-Control'), 'immutable')) && $ok;
        $ok = $this->check('artifact varies on Accept-Encoding', str_contains(strtolower($gzip->header('Vary')), 'accept-encoding')) && $ok;

        $this->line(sprintf('  CDN threshold: %s compressed (question to reopen above 1 MB)', number_format($sent / 1024, 0, ',', ' ').' kB'));

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function decoded(string $body, string $encoding): string
    {
        return strtolower($encoding) === 'gzip' ? (string) @gzdecode($body) : $body;
    }

    private function check(string $label, bool $passed): bool
    {
        $passed ? $this->info("  OK   {$label}") : $this->error("  FAIL {$label}");

        return $passed;
    }
}
