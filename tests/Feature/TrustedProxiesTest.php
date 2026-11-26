<?php

// SPDX-License-Identifier: Apache-2.0

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;

function client_ip_seen_with(string $proxies, string $remote, string $forwarded): string
{
    config(['odac.trusted_proxies' => $proxies]);
    (new AppServiceProvider(app()))->boot();
    Route::get('/_test/ip', fn (\Illuminate\Http\Request $r) => $r->ip());

    return test()->call('GET', '/_test/ip', server: ['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => $forwarded])->getContent();
}

it('ignores X-Forwarded-For when no proxy is named', function () {
    expect(client_ip_seen_with('', '203.0.113.9', '198.51.100.1'))->toBe('203.0.113.9');
});

it('honours X-Forwarded-For only from a named proxy', function () {
    expect(client_ip_seen_with('10.0.0.2', '10.0.0.2', '198.51.100.1'))->toBe('198.51.100.1')
        ->and(client_ip_seen_with('10.0.0.2', '203.0.113.9', '198.51.100.1'))->toBe('203.0.113.9');
});

it('refuses to boot with a wildcard', function () {
    config(['odac.trusted_proxies' => '*']);
    (new AppServiceProvider(app()))->boot();
})->throws(InvalidArgumentException::class);
