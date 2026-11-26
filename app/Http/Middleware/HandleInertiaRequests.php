<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Middleware;

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
     * The read-only site has no session: nothing user-specific is shared, so
     * pages can carry a public Cache-Control.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            'app' => [
                'name' => 'ODAC',
                'contribute_url' => (string) config('odac.contribute_url') ?: null,
            ],
        ];
    }
}
