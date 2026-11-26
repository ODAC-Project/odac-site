<?php

// SPDX-License-Identifier: Apache-2.0

/** Every key of a dictionary, dotted (`help.q1`, `common.devices_n.one`). */
function odac_dictionary_keys(array $node, string $prefix = ''): array
{
    $keys = [];
    foreach ($node as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        $keys = array_merge($keys, is_array($value) ? odac_dictionary_keys($value, $path) : [$path]);
    }

    return $keys;
}

function odac_dictionary(string $locale): array
{
    return json_decode((string) file_get_contents(__DIR__."/../../resources/js/i18n/{$locale}.json"), true, 64, JSON_THROW_ON_ERROR);
}

it('has exactly the same keys in French and in English', function () {
    $fr = odac_dictionary_keys(odac_dictionary('fr'));
    $en = odac_dictionary_keys(odac_dictionary('en'));

    expect(array_values(array_diff($fr, $en)))->toBe([], 'keys missing in English')
        ->and(array_values(array_diff($en, $fr)))->toBe([], 'keys missing in French');
});

it('has no empty string and keeps the same placeholders in both languages', function () {
    $fr = odac_dictionary('fr');
    $en = odac_dictionary('en');
    foreach (odac_dictionary_keys($fr) as $key) {
        $a = data_get($fr, $key);
        $b = data_get($en, $key);
        expect(trim($a))->not->toBe('', "fr {$key} is empty")
            ->and(trim($b))->not->toBe('', "en {$key} is empty");
        preg_match_all('/\{\w+\}/', $a, $pa);
        preg_match_all('/\{\w+\}/', $b, $pb);
        sort($pa[0]);
        sort($pb[0]);
        expect($pb[0])->toBe($pa[0], "placeholders differ for {$key}");
    }
});

it('uses in the pages only keys that exist', function () {
    $keys = array_flip(odac_dictionary_keys(odac_dictionary('fr')));
    $prefixes = [];
    foreach (array_keys($keys) as $key) {
        foreach (explode('.', $key) as $i => $_) {
            $prefixes[implode('.', array_slice(explode('.', $key), 0, $i + 1))] = true;
        }
    }
    $root = dirname(__DIR__, 2).'/resources/js';
    // scandir, not glob or a directory iterator: those were seen failing on a
    // macOS Docker bind mount after the feature tests had run.
    $walk = function (string $dir) use (&$walk): array {
        $out = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = "{$dir}/{$name}";
            $out = array_merge($out, is_dir($path) ? $walk($path) : (str_ends_with($name, '.jsx') ? [$path] : []));
        }

        return $out;
    };
    $files = $walk($root);
    expect(count($files))->toBeGreaterThan(10);
    $unknown = [];
    foreach ($files as $file) {
        preg_match_all("/\\bt\\('([a-z0-9_.]+)'/", (string) file_get_contents($file), $m);
        foreach ($m[1] as $key) {
            if (! isset($keys[$key]) && ! isset($prefixes[$key])) {
                $unknown[] = basename($file).": {$key}";
            }
        }
    }

    expect($unknown)->toBe([]);
});
