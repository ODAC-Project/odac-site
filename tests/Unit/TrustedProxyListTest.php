<?php

// SPDX-License-Identifier: Apache-2.0

use App\Support\Odac\TrustedProxyList;

it('parses a named list of proxies', function () {
    expect(TrustedProxyList::parse(' 10.0.0.2, 192.168.1.0/24 ,, 2001:db8::1 '))
        ->toBe(['10.0.0.2', '192.168.1.0/24', '2001:db8::1']);
    expect(TrustedProxyList::parse(''))->toBe([]);
});

it('refuses a wildcard or an over-wide range', function (string $raw) {
    TrustedProxyList::parse($raw);
})->with(['*', '**', '10.0.0.1,*', '0.0.0.0/0', '10.0.0.0/4', 'proxy.example.org', '10.0.0.1/abc'])
    ->throws(InvalidArgumentException::class);
