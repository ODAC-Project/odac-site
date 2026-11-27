<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Jobs;

use App\Models\OdacProposal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a proposal into an issue on the BDD, where curators already work.
 * The issue carries the pseudonym, never the account id or an address, and
 * states that the licence was accepted on the form (CONTRIBUTING: "Contributions
 * made through the website's form record the same agreement with a checkbox").
 */
class ForwardProposalToGitHub implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $proposalId) {}

    public function handle(): void
    {
        $token = (string) config('odac.proposals.github_token');
        $proposal = OdacProposal::find($this->proposalId);
        if ($token === '' || ! $proposal || $proposal->github_issue_url) {
            return;
        }
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post('https://api.github.com/repos/'.config('odac.proposals.github_repo').'/issues', [
                'title' => self::title($proposal),
                'body' => self::body($proposal),
            ]);
        if ($response->successful() && is_string($url = $response->json('html_url'))) {
            $proposal->update(['github_issue_url' => $url, 'status' => 'forwarded']);
        } else {
            Log::warning('odac: proposal not forwarded to GitHub', ['id' => $proposal->id, 'status' => $response->status()]);
        }
    }

    public static function title(OdacProposal $p): string
    {
        $what = ['new_device' => 'Nouvel appareil', 'change' => 'Correction', 'comment' => 'Commentaire'][$p->kind] ?? $p->kind;
        $subject = $p->odac_id ?? $p->device_name ?? '';

        return mb_substr("[site] {$what} : {$subject}".($p->field ? " ({$p->field})" : ''), 0, 200);
    }

    public static function body(OdacProposal $p): string
    {
        $lines = ["Proposition envoyée depuis odac.tune.audio par **{$p->author_pseudonym}** (compte Mozaik Labs).", ''];
        if ($p->odac_id) {
            $lines[] = "- Fiche : [{$p->odac_id}](https://odac.tune.audio/{$p->odac_id})";
        }
        if ($p->device_name) {
            $lines[] = "- Appareil : {$p->device_name}";
        }
        if ($p->field) {
            $lines[] = "- Champ : `{$p->field}`";
        }
        if ($p->source_url) {
            $lines[] = "- Source indiquée : {$p->source_url}";
        }
        $lines[] = '';
        // Quoted, so nothing in the text is read as markup that pings or links.
        foreach (preg_split('/\R/', $p->message) as $line) {
            $lines[] = '> '.str_replace('@', "@\u{200B}", $line);
        }
        $lines[] = '';
        $lines[] = "Licence acceptée sur le formulaire le {$p->licence_agreed_at->toDateString()} : CC BY-SA 4.0 pour les données (voir docs/CONTRIBUTING.md).";
        $lines[] = 'À transformer en fiche ou en preuve par un curateur, avec sa source.';

        return implode("\n", $lines);
    }
}
