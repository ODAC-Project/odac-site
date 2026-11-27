<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Console\Commands;

use App\Models\OdacProposal;
use Illuminate\Console\Command;

class ListProposals extends Command
{
    protected $signature = 'odac:proposals {--all : include those already forwarded}';

    protected $description = 'List the proposals sent from the site (for a curator)';

    public function handle(): int
    {
        $rows = OdacProposal::query()
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('github_issue_url'))
            ->latest()->limit(200)->get();
        $this->table(['#', 'date', 'type', 'fiche / appareil', 'champ', 'par', 'message', 'issue'], $rows->map(fn ($p) => [
            $p->id, $p->created_at?->toDateString(), $p->kind, $p->odac_id ?? $p->device_name, $p->field,
            $p->author_pseudonym, mb_strimwidth($p->message, 0, 60, '…'), $p->github_issue_url,
        ])->all());

        return self::SUCCESS;
    }
}
