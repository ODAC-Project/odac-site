<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

use InvalidArgumentException;

/**
 * The fingerprint carried by the artifact name, computed from its bytes.
 */
final class Fingerprint
{
    public static function ofFile(string $path, ?string $algorithm = null): string
    {
        $algorithm ??= (string) config('odac.fingerprint', 'sha256-16');

        return match ($algorithm) {
            'sha256-16' => substr((string) hash_file('sha256', $path), 0, 16),
            default => throw new InvalidArgumentException("Unknown ODAC fingerprint algorithm \"{$algorithm}\"."),
        };
    }
}
