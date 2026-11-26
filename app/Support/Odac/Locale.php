<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Support\Odac;

use Illuminate\Http\Request;

/**
 * The visitor's language, decided without any server state:
 *  1. `?lang=fr|en` (a shared link carries its language);
 *  2. the `odac_lang` preference cookie, written by the FR/EN switch in the
 *     browser (the server never sets a cookie);
 *  3. Accept-Language: French when the browser's first language is French,
 *     English for everything else.
 *
 * The dictionaries are the JSON files the pages use (resources/js/i18n), so
 * the few strings rendered by Blade never drift from the React ones.
 */
final class Locale
{
    public const SUPPORTED = ['fr', 'en'];

    public const DEFAULT = 'en';

    public const COOKIE = 'odac_lang';

    public static function resolve(Request $request): string
    {
        $query = $request->query('lang');
        if (is_string($query) && in_array($query, self::SUPPORTED, true)) {
            return $query;
        }
        $cookie = $request->cookies->get(self::COOKIE);
        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        return self::fromAcceptLanguage((string) $request->headers->get('Accept-Language', ''));
    }

    public static function fromAcceptLanguage(string $header): string
    {
        $best = null;
        $bestQ = -1.0;
        foreach (explode(',', $header) as $part) {
            $bits = array_map('trim', explode(';', $part));
            $tag = strtolower((string) array_shift($bits));
            if ($tag === '' || $tag === '*') {
                continue;
            }
            $q = 1.0;
            foreach ($bits as $bit) {
                if (str_starts_with($bit, 'q=')) {
                    $q = (float) substr($bit, 2);
                }
            }
            if ($q > $bestQ) {
                [$best, $bestQ] = [$tag, $q];
            }
        }

        return $best !== null && $bestQ > 0 && ($best === 'fr' || str_starts_with($best, 'fr-')) ? 'fr' : self::DEFAULT;
    }

    /** One string of a dictionary, by dotted key (Blade side). */
    public static function text(string $key, ?string $locale = null): string
    {
        static $dictionaries = [];
        $locale ??= app()->getLocale();
        $dictionaries[$locale] ??= json_decode(
            (string) file_get_contents(resource_path("js/i18n/{$locale}.json")), true, 64, JSON_THROW_ON_ERROR,
        );
        $value = data_get($dictionaries[$locale], $key);

        return is_string($value) ? $value : $key;
    }
}
