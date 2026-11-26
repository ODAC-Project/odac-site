<?php

// SPDX-License-Identifier: Apache-2.0

return [

    /*
    | Where the CI publishes `latest.json` (the artifact sits next to it, its
    | name in the pointer resolves relative to it). The URL or path of
    | latest.json itself, or of the folder holding it.
    */
    'source' => env('ODAC_SOURCE', 'https://raw.githubusercontent.com/ODAC-Project/ODAC/main/dist/latest.json'),

    /*
    | Local directory (under storage/app) that keeps EVERY ingested artifact.
    | Artifacts are content-addressed: old ones are never deleted, a client
    | holding an old URL must still get the same bytes behind it.
    */
    'artifacts_dir' => 'odac/artifacts',

    /*
    | How the fingerprint in the artifact name is derived from its bytes.
    | `sha256-16`: the first 16 hex characters of the SHA-256 of the file,
    | as tools/build.py computes it (`sha256(body).hexdigest()[:16]`).
    */
    'fingerprint' => env('ODAC_FINGERPRINT', 'sha256-16'),

    // Refuse anything larger (the artifact is ~5.5 MB raw on 2026-11-26).
    'max_artifact_bytes' => (int) env('ODAC_MAX_ARTIFACT_BYTES', 64 * 1024 * 1024),

    'http_timeout' => (int) env('ODAC_HTTP_TIMEOUT', 30),

    /*
    | Exact Cache-Control values, right from day one (a CDN may come later on
    | the same host name, and it will obey these).
    |  - the pointer is the only mutable file: short cache, then revalidate
    |    with If-None-Match (a 304 costs one indexed read);
    |  - an artifact URL carries its fingerprint, so its bytes never change.
    */
    'cache' => [
        'pointer' => 'public, max-age=300, must-revalidate',
        'artifact' => 'public, max-age=31536000, immutable',
        'pages' => 'public, max-age=300',
        'missing' => 'no-store',
    ],

    /*
    | Reverse proxies allowed to set X-Forwarded-*: an explicit, NAMED list
    | (comma separated IPs or CIDR). Empty means nginx is the first hop and
    | REMOTE_ADDR is the client. `*` is refused at boot: it would let any
    | client choose its own IP through X-Forwarded-For.
    */
    'trusted_proxies' => env('ODAC_TRUSTED_PROXIES', ''),

    'per_page' => 48,

    // Where the "contribuer" button leads (v1: the contribution flow lives on
    // mozaiklabs.fr). Empty: the button is not shown.
    'contribute_url' => env('ODAC_CONTRIBUTE_URL', ''),
];
