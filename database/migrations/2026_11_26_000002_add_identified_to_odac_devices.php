<?php

// SPDX-License-Identifier: Apache-2.0

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity becomes its own column, apart from the content level: a record
 * can be complete at level 2 and still be unrecognisable by a player.
 *
 * The projection is rebuilt at each fingerprint, so existing rows only get
 * their real value at the next ingestion: run `odac:ingest --force` after
 * deploying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odac_devices', function (Blueprint $table) {
            $table->boolean('identified')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('odac_devices', function (Blueprint $table) {
            $table->dropIndex(['identified']);
            $table->dropColumn('identified');
        });
    }
};
