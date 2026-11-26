<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One device of the current snapshot, as projected for reading.
 *
 * @property string $odac_id
 * @property string $brand_slug
 * @property string $model_slug
 * @property string $brand_name
 * @property string $model_name
 * @property string $kind
 * @property ?int $level content level: 3, 2 or 0 (below 2)
 * @property bool $identified USB identity or resolved match
 * @property bool $is_contested
 * @property bool $has_open_doubt
 * @property ?string $superseded_by
 * @property array $data
 */
class OdacDevice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'identified' => 'boolean',
            'is_contested' => 'boolean',
            'has_open_doubt' => 'boolean',
            'data' => 'array',
        ];
    }
}
