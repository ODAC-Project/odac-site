<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

/**
 * Two measures, kept apart on purpose.
 *
 * IDENTITY (the level-1 gate of `vocabulary/completeness`): an exact USB
 * identity OR a resolved `device_match`. It says whether a player can
 * RECOGNISE the device. Without it the record resolves exactly like its
 * brand's catch-all, however complete its content.
 *
 * CONTENT (levels 2 and 3), computed from the vocabulary's field lists and
 * nothing else, the same reading as the ODAC verifier (`tools/verifier.py`,
 * `_complet`):
 *  - level 2 (what the device accepts) and level 3 (what it does inside)
 *    are complete when every field of their list is present; `inputs` needs
 *    a non-empty input block;
 *  - level 3 is only reached on top of level 2;
 *  - otherwise 0: the content is below level 2.
 *
 * Until 2026-11-26 the two were folded into one number (level 1 meant
 * "identified, content below 2"), which tested content first: 89 of the 91
 * records shown at level 2 had no identity at all, and the number hid it.
 *
 * Without the vocabulary the content level is unknown (null), never guessed.
 * Identity does not depend on it.
 */
final class Completeness
{
    /**
     * Content level: 3, 2, or 0 (below level 2). Never 1: identity is
     * {@see identified()}, a separate measure.
     *
     * @param  array<int, list<string>>  $levels
     */
    public static function level(array $data, array $levels): ?int
    {
        if ($levels === []) {
            return null;
        }
        if (isset($levels[2]) && self::complete($data, $levels[2])) {
            return isset($levels[3]) && self::complete($data, $levels[3]) ? 3 : 2;
        }

        return 0;
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

    /** The identity gate (level 1 of the vocabulary): an exact USB identity OR a resolved match. */
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
