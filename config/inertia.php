<?php

// SPDX-License-Identifier: Apache-2.0
// Inertia v3 looks for pages in resources/js/pages (lower case) by default.
// Ours live in resources/js/Pages: invisible on a case-insensitive Mac,
// fatal on Linux (CI, production build). Declared here explicitly.

return [
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/Pages')],
        'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],
];
