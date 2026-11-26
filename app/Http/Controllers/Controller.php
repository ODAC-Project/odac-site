<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Controllers;

use App\Models\OdacSnapshot;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    /**
     * A public page: shared cache allowed, and it varies with the Inertia
     * header and with the visitor's language (Accept-Language, or the
     * `odac_lang` preference cookie).
     */
    protected function publicPage(Response $response): Response
    {
        $response->headers->set('Cache-Control', (string) config('odac.cache.pages'));
        $response->setVary(['X-Inertia', 'Accept-Language', 'Cookie']);

        return $response;
    }

    /**
     * A page that only changes with the snapshot: the ETag comes from stored
     * values (fingerprint, address, assets version, language, representation)
     * and is settled BEFORE the page is built.
     *
     * @param  Closure(): \Inertia\Response  $build
     */
    protected function snapshotPage(Request $request, Closure $build): Response
    {
        $etag = substr(hash('sha256', implode('|', [
            OdacSnapshot::current()?->fingerprint,
            $request->path(),
            $request->getQueryString(),
            Inertia::getVersion(),
            app()->getLocale(),
            $request->header('X-Inertia') ? 'json' : 'html',
        ])), 0, 32);

        $probe = new Response;
        $probe->setEtag($etag);
        $this->publicPage($probe);
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        $response = $build()->toResponse($request);
        $response->setEtag($etag);

        return $this->publicPage($response);
    }
}
