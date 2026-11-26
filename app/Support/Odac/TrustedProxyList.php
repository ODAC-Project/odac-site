<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

use InvalidArgumentException;

/**
 * Parses the named list of trusted reverse proxies.
 *
 * Never a wildcard: with `*`, every client could pick its own address through
 * X-Forwarded-For, and per-address limits would mean nothing.
 */
final class TrustedProxyList
{
    /** @return list<string> */
    public static function parse(string|array|null $raw): array
    {
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);
        $proxies = [];

        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if (str_contains($item, '*')) {
                throw new InvalidArgumentException('ODAC_TRUSTED_PROXIES must name its proxies: a wildcard is refused.');
            }
            [$address, $mask] = array_pad(explode('/', $item, 2), 2, null);
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException("ODAC_TRUSTED_PROXIES: \"{$item}\" is not an IP address or CIDR range.");
            }
            $max = str_contains($address, ':') ? 128 : 32;
            if ($mask !== null && (! ctype_digit($mask) || (int) $mask > $max || (int) $mask < 8)) {
                throw new InvalidArgumentException("ODAC_TRUSTED_PROXIES: \"{$item}\" has an invalid or too wide mask.");
            }
            $proxies[] = $item;
        }

        return array_values(array_unique($proxies));
    }
}
