<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Console\Commands;

use App\Actions\Odac\IngestArtifact;
use App\Support\Odac\ArtifactSource;
use App\Support\Odac\IngestionFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OdacIngest extends Command
{
    protected $signature = 'odac:ingest
        {--source= : Base URL of dist/ or a local directory (default: ODAC_SOURCE)}
        {--force : Re-project even if the fingerprint is unchanged}';

    protected $description = 'Reads latest.json and ingests the ODAC artifact if its fingerprint changed';

    public function handle(IngestArtifact $ingest): int
    {
        $started = hrtime(true);
        $lock = Cache::lock('odac:ingest', 600);
        if (! $lock->get()) {
            $this->warn('Another ingestion is running.');

            return self::SUCCESS;
        }

        try {
            $source = new ArtifactSource(
                (string) ($this->option('source') ?: config('odac.source')),
                (int) config('odac.http_timeout'),
                (int) config('odac.max_artifact_bytes'),
            );
            ['status' => $status, 'snapshot' => $snapshot] = $ingest($source, (bool) $this->option('force'));
        } catch (IngestionFailed $e) {
            $this->error('ODAC ingestion refused: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        if ($status === IngestArtifact::UNCHANGED) {
            $this->info("unchanged: {$snapshot->filename} ({$snapshot->fingerprint})");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s: %s (%s, %d entries, %d bytes, %d compressed) in %.2f s, peak memory %.1f MB',
            $status, $snapshot->filename, $snapshot->fingerprint, $snapshot->entries,
            $snapshot->bytes, $snapshot->gzip_bytes,
            (hrtime(true) - $started) / 1e9, memory_get_peak_usage(true) / 1048576,
        ));

        return self::SUCCESS;
    }
}
