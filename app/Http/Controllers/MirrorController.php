<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Controllers;

use App\Models\OdacSnapshot;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror: `latest.json` and the fingerprinted artifacts.
 *
 * The ETag is the STORED fingerprint (the CirclePlaylistController pattern,
 * `setEtag($version)`), never a hash of the rendered body: If-None-Match is
 * settled after one indexed read, before anything is built or read from disk.
 */
class MirrorController extends Controller
{
    public function latest(Request $request): Response
    {
        $snapshot = OdacSnapshot::current();
        if ($snapshot === null) {
            return $this->missing('no_snapshot');
        }

        $response = $this->conditional($snapshot->fingerprint, (string) config('odac.cache.pointer'));
        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->headers->set('Content-Type', 'application/json');

        return $response->setContent(json_encode($snapshot->pointer(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function artifact(Request $request, string $name): Response
    {
        $snapshot = OdacSnapshot::query()
            ->select(['id', 'fingerprint', 'filename'])
            ->where('filename', $name)
            ->first();
        if ($snapshot === null) {
            return $this->missing('unknown_artifact');
        }

        // The bytes behind a fingerprinted URL never change: same ETag for the
        // raw and the gzip representations would be wrong, so suffix it.
        $gzip = self::acceptsGzip((string) $request->headers->get('Accept-Encoding', ''));
        $etag = $snapshot->fingerprint.($gzip ? '-gz' : '');

        $probe = $this->conditional($etag, (string) config('odac.cache.artifact'));
        $probe->setVary('Accept-Encoding');
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        $path = $gzip ? $snapshot->gzipPath() : $snapshot->artifactPath();
        if (! is_file($path)) {
            report(new \RuntimeException("ODAC artifact missing on disk: {$path}"));

            return $this->missing('artifact_unavailable', 503);
        }

        $response = new BinaryFileResponse($path, 200, [], true, null, false, false);
        $response->headers->add($probe->headers->all());
        $response->headers->set('Content-Type', 'application/x-ndjson; charset=utf-8');
        if ($gzip) {
            $response->headers->set('Content-Encoding', 'gzip');
        }

        return $response;
    }

    /**
     * True when the client takes gzip (q-values honoured, `gzip;q=0` refuses).
     */
    public static function acceptsGzip(string $header): bool
    {
        $wildcard = null;
        foreach (explode(',', strtolower($header)) as $part) {
            $bits = array_map('trim', explode(';', $part));
            $coding = array_shift($bits);
            $q = 1.0;
            foreach ($bits as $bit) {
                if (str_starts_with($bit, 'q=')) {
                    $q = (float) substr($bit, 2);
                }
            }
            if ($coding === 'gzip' || $coding === 'x-gzip') {
                return $q > 0;
            }
            if ($coding === '*') {
                $wildcard = $q > 0;
            }
        }

        return $wildcard ?? false;
    }

    private function conditional(string $etag, string $cacheControl): Response
    {
        $response = new Response;
        $response->setEtag($etag);
        $response->headers->set('Cache-Control', $cacheControl);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Expose-Headers', 'ETag');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function missing(string $code, int $status = 404): Response
    {
        return response()->json(['error' => $code], $status)
            ->header('Cache-Control', (string) config('odac.cache.missing'));
    }
}
