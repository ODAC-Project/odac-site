<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

/**
 * Completeness levels, computed from `vocabulary/completeness` and nothing
 * else: the same reading as the ODAC verifier (`tools/verifier.py`,
 * `_complet`). A level names a decision the facts allow, never a field count.
 *
 *  - level 2 (what the device accepts) and level 3 (what it does inside)
 *    are complete when every field of their list is present; `inputs` needs
 *    a non-empty input block;
 *  - level 3 is only reached on top of level 2;
 *  - below level 2, a record with a USB identity or a resolved match is
 *    level 1 (identified); otherwise 0.
 *
 * Without the vocabulary the level is unknown (null), never guessed.
 */
final class Completeness
{
    /** @param array<int, list<string>> $levels */
    public static function level(array $data, array $levels): ?int
    {
        if ($levels === []) {
            return null;
        }
        if (isset($levels[2]) && self::complete($data, $levels[2])) {
            return isset($levels[3]) && self::complete($data, $levels[3]) ? 3 : 2;
        }

        return self::identified($data) ? 1 : 0;
    }

    /** @param list<string> $fields */
    public static function complete(array $data, array $fields): bool
    {
        return self::missing($data, $fields) === [];
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    public static function missing(array $data, array $fields): array
    {
        $inputs = is_array($data['inputs'] ?? null) ? $data['inputs'] : [];
        $missing = [];
        foreach ($fields as $field) {
            if ($field === 'inputs') {
                $ok = $inputs !== [];
            } elseif (str_contains($field, '.')) {
                $key = explode('.', $field, 2)[1];
                $ok = $inputs !== [] && array_reduce($inputs, fn ($all, $v) => $all && is_array($v) && array_key_exists($key, $v), true);
            } else {
                $ok = array_key_exists($field, $data);
            }
            if (! $ok) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /** Level 1 gate: an exact USB identity OR a resolved match. */
    public static function identified(array $data): bool
    {
        foreach ((array) ($data['usb'] ?? []) as $usb) {
            if (is_array($usb) && ! empty($usb['vid']) && ! empty($usb['pid'])) {
                return true;
            }
        }

        return ! empty($data['device_match']);
    }
}
