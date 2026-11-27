<?php

// SPDX-License-Identifier: Apache-2.0

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text proposals from people signed in with a Mozaik Labs account: a new
 * device, a correction, or a comment on a record. They are NOT data: a curator
 * reads them and turns what holds up into a claim file in the BDD, with its
 * source. Each one is forwarded to the BDD's GitHub issues when a token is set.
 *
 * Who wrote it is kept as the account's opaque id and the pseudonym the person
 * chose to be shown under; no e-mail address is stored or forwarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odac_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);              // new_device | change | comment
            $table->string('odac_id')->nullable()->index();
            $table->string('field')->nullable();
            $table->string('device_name')->nullable(); // a new device, in the person's words
            $table->text('message');
            $table->string('source_url', 2048)->nullable();
            $table->string('author_id');             // Mozaik Labs account id (opaque)
            $table->string('author_pseudonym', 64);
            $table->timestamp('licence_agreed_at');
            $table->string('status', 16)->default('new');
            $table->string('github_issue_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odac_proposals');
    }
};
