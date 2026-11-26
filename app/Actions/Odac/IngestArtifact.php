<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Actions\Odac;

use App\Models\OdacSnapshot;
use App\Support\Odac\ArtifactReader;
use App\Support\Odac\ArtifactSource;
use App\Support\Odac\Fingerprint;
use App\Support\Odac\IngestionFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Reads `latest.json`; downloads the artifact only when the fingerprint has
 * changed; verifies it; keeps it (old artifacts are never deleted); makes it
 * current and rebuilds the projection, in one transaction.
 *
 * Any failure leaves the current snapshot untouched: the mirror keeps serving
 * the last good fingerprint.
 */
final class IngestArtifact
{
    public const UNCHANGED = 'unchanged';

    public const INGESTED = 'ingested';

    public const REACTIVATED = 'reactivated';

    public function __construct(private readonly ProjectSnapshot $project) {}

    /** @return array{status: string, snapshot: OdacSnapshot} */
    public function __invoke(ArtifactSource $source, bool $force = false): array
    {
        $pointer = $source->pointer();
        $current = OdacSnapshot::current();

        if (! $force && $current !== null && $current->fingerprint === $pointer->fingerprint) {
            return ['status' => self::UNCHANGED, 'snapshot' => $current];
        }

        $known = OdacSnapshot::query()->where('fingerprint', $pointer->fingerprint)->first();
        if ($known !== null && is_file($known->artifactPath()) && is_file($known->gzipPath())) {
            // Already on disk (a rollback upstream, or --force): no download.
            $this->verify($known->artifactPath(), $pointer->fingerprint);
            $scan = $this->scan($known->artifactPath(), $pointer->entries);
            $this->activate($known, $scan['brands']);

            return ['status' => self::REACTIVATED, 'snapshot' => $known->refresh()];
        }

        $dir = storage_path('app/'.config('odac.artifacts_dir'));
        File::ensureDirectoryExists($dir);
        $tmp = $dir.'/.incoming-'.bin2hex(random_bytes(6));

        try {
            $bytes = $source->download($pointer->filename, $tmp);
            $sha256 = (string) hash_file('sha256', $tmp);
            $this->verify($tmp, $pointer->fingerprint);
            $scan = $this->scan($tmp, $pointer->entries);

            $final = $dir.'/'.$pointer->filename;
            $gzip = $this->compress($tmp, $final.'.gz');
            if (! rename($tmp, $final)) {
                throw new IngestionFailed("Could not move the artifact to {$final}.");
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        $snapshot = $known ?? new OdacSnapshot;
        $snapshot->fill([
            'version' => $pointer->version,
            'fingerprint' => $pointer->fingerprint,
            'filename' => $pointer->filename,
            'entries' => $scan['entries'],
            'bytes' => $bytes,
            'gzip_bytes' => $gzip,
            'sha256' => $sha256,
            'source' => mb_substr($source->describe($pointer->filename), 0, 500),
            'ingested_at' => Carbon::now(),
        ]);

        $this->activate($snapshot, $scan['brands']);

        return ['status' => self::INGESTED, 'snapshot' => $snapshot->refresh()];
    }

    private function verify(string $path, string $expected): void
    {
        $actual = Fingerprint::ofFile($path);
        if (! hash_equals($expected, $actual)) {
            throw new IngestionFailed("Fingerprint mismatch: latest.json announces {$expected}, the bytes give {$actual}.");
        }
    }

    private function scan(string $path, int $announced): array
    {
        $scan = ArtifactReader::scan($path);
        if ($scan['entries'] !== $announced) {
            throw new IngestionFailed("latest.json announces {$announced} entries, the artifact holds {$scan['entries']}.");
        }

        return $scan;
    }

    /** Pre-compressed once, so that what is SENT is compressed, whatever nginx does. */
    private function compress(string $source, string $target): int
    {
        $tmp = $target.'.tmp';
        $in = fopen($source, 'rb');
        $out = gzopen($tmp, 'wb9');
        if ($in === false || $out === false) {
            throw new IngestionFailed('Could not compress the artifact.');
        }
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 16));
        }
        fclose($in);
        gzclose($out);
        rename($tmp, $target);
        clearstatcache(true, $target);

        return (int) filesize($target);
    }

    private function activate(OdacSnapshot $snapshot, array $brands): void
    {
        DB::transaction(function () use ($snapshot, $brands) {
            $snapshot->is_current = false;
            $snapshot->save();
            ($this->project)($snapshot, $brands);
            OdacSnapshot::query()->where('id', '!=', $snapshot->id)->update(['is_current' => false]);
            $snapshot->is_current = true;
            $snapshot->activated_at = Carbon::now();
            $snapshot->save();
        });
    }
}
