<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdacProposal extends Model
{
    public const KINDS = ['new_device', 'change', 'comment'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['licence_agreed_at' => 'datetime'];
    }
}
