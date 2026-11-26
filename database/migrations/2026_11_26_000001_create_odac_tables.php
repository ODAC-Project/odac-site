<?php

// SPDX-License-Identifier: Apache-2.0

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every artifact ever ingested. The row of the current one is the
        // site's only real state: "which fingerprint do I hold".
        Schema::create('odac_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32);
            $table->string('fingerprint', 16)->unique();
            $table->string('filename', 80)->unique();
            $table->unsignedInteger('entries');
            $table->unsignedBigInteger('bytes');
            $table->unsignedBigInteger('gzip_bytes');
            $table->char('sha256', 64);
            $table->string('source', 500);
            $table->boolean('is_current')->default(false)->index();
            $table->timestamp('ingested_at');
            $table->timestamp('activated_at')->nullable();
        });

        // Readable projection of the CURRENT snapshot. Never the source of
        // truth: it is thrown away and rebuilt at each new fingerprint.
        Schema::create('odac_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('odac_snapshots')->cascadeOnDelete();
            $table->string('odac_id', 200)->unique();
            $table->string('brand_slug', 100)->index();
            $table->string('model_slug', 100);
            $table->string('brand_name', 200);
            $table->string('model_name', 200);
            $table->string('kind', 40)->index();
            $table->unsignedTinyInteger('level')->nullable();
            $table->boolean('is_contested')->default(false);
            $table->boolean('has_open_doubt')->default(false);
            $table->string('superseded_by', 200)->nullable();
            $table->text('search_text');
            $table->json('data');
            $table->index(['brand_name', 'model_name']);
        });

        // Paths that answer with a 301: spelling aliases. A "neighbour" alias
        // is another product and is never stored here.
        Schema::create('odac_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('odac_snapshots')->cascadeOnDelete();
            $table->string('path', 200)->unique();
            $table->string('odac_id', 200);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odac_aliases');
        Schema::dropIfExists('odac_devices');
        Schema::dropIfExists('odac_snapshots');
    }
};
