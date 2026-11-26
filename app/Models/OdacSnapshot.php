<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $version
 * @property string $fingerprint
 * @property string $filename
 * @property int $entries
 * @property int $bytes
 * @property int $gzip_bytes
 * @property string $sha256
 * @property bool $is_current
 */
class OdacSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entries' => 'integer',
            'bytes' => 'integer',
            'gzip_bytes' => 'integer',
            'is_current' => 'boolean',
            'ingested_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }

    /** One indexed read, nothing else: this is what a 304 costs. */
    public static function current(): ?self
    {
        return static::query()
            ->select(['id', 'version', 'fingerprint', 'filename', 'entries'])
            ->where('is_current', true)
            ->first();
    }

    /** Pointer served by the mirror, built from stored values only. */
    public function pointer(): array
    {
        return [
            'version' => $this->version,
            'fingerprint' => $this->fingerprint,
            'url' => 'artifacts/'.$this->filename,
            'entries' => $this->entries,
        ];
    }

    public function artifactPath(): string
    {
        return storage_path('app/'.config('odac.artifacts_dir').'/'.$this->filename);
    }

    public function gzipPath(): string
    {
        return $this->artifactPath().'.gz';
    }
}
