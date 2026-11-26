<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Middleware;

use App\Models\OdacSnapshot;
use App\Support\Odac\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * The read-only site has no session: nothing user-specific is shared
     * (the language is a property of the response, and the response varies
     * on it), so pages can carry a public Cache-Control.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            'app' => [
                'name' => 'ODAC',
                'contribute_url' => (string) config('odac.contribute_url') ?: null,
                'github_url' => 'https://github.com/ODAC-Project/ODAC',
            ],
            'locale' => app()->getLocale(),
            'locales' => Locale::SUPPORTED,
            // Lazy: resolved only when a page is rendered, so that a 304 on the
            // mirror still costs a single query.
            'snapshot' => fn () => ($snapshot = OdacSnapshot::current()) ? [
                'version' => $snapshot->version,
                'fingerprint' => $snapshot->fingerprint,
                // Versions are dated (`2026.11.25`): the date of the snapshot.
                'date' => preg_match('/^(\d{4})\.(\d{2})\.(\d{2})/', $snapshot->version, $m) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null,
            ] : null,
        ];
    }
}
