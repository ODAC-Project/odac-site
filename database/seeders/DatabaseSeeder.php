<?php

// SPDX-License-Identifier: Apache-2.0

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The ODAC site has no seed data: its only state is the artifact it ingests
 * (`php artisan odac:ingest`).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
