<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Providers;

use App\Support\Odac\TrustedProxyList;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // A NAMED list, never `*` (TrustedProxyList throws on a wildcard).
        // Empty: nginx is the first hop and REMOTE_ADDR is the client.
        TrustProxies::at(TrustedProxyList::parse(config('odac.trusted_proxies')));

        // Redirects (aliases, replaced sheets) and pagination links are
        // absolute: behind TLS they must say https whatever the hop says.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }
    }
}
