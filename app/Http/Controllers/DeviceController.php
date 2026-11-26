<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Controllers;

use App\Actions\Odac\BuildDeviceSheet;
use App\Models\OdacAlias;
use App\Models\OdacDevice;
use App\Models\OdacSnapshot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DeviceController extends Controller
{
    public const KINDS = ['dac', 'network_player'];

    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'brand' => (string) $request->query('brand', ''),
            'kind' => in_array($request->query('kind'), self::KINDS, true) ? (string) $request->query('kind') : '',
            'contested' => $request->boolean('contested'),
            'doubt' => $request->boolean('doubt'),
        ];

        $query = OdacDevice::query()
            ->select(['odac_id', 'brand_slug', 'model_slug', 'brand_name', 'model_name', 'kind', 'level', 'is_contested', 'has_open_doubt'])
            ->whereNull('superseded_by');

        if ($filters['q'] !== '') {
            $like = '%'.addcslashes(mb_strtolower($filters['q']), '%_\\').'%';
            $query->whereRaw("search_text LIKE ? ESCAPE '\\'", [$like]);
        }
        if ($filters['brand'] !== '') {
            $query->where('brand_slug', $filters['brand']);
        }
        if ($filters['kind'] !== '') {
            $query->where('kind', $filters['kind']);
        }
        if ($filters['contested']) {
            $query->where('is_contested', true);
        }
        if ($filters['doubt']) {
            $query->where('has_open_doubt', true);
        }

        $devices = $query->orderBy('brand_name')->orderBy('model_name')
            ->paginate((int) config('odac.per_page'))
            ->withQueryString();

        $brands = OdacDevice::query()
            ->select(['brand_slug', 'brand_name'])
            ->distinct()
            ->orderBy('brand_name')
            ->get();

        $snapshot = OdacSnapshot::current();

        return $this->publicPage(Inertia::render('Devices/Index', [
            'devices' => $devices,
            'filters' => $filters,
            'brands' => $brands,
            'snapshot' => $snapshot ? ['version' => $snapshot->version, 'fingerprint' => $snapshot->fingerprint] : null,
        ])->toResponse($request));
    }

    public function show(Request $request, string $brand, string $model, BuildDeviceSheet $sheet): Response
    {
        $id = "{$brand}/{$model}";
        $snapshot = OdacSnapshot::current();

        $device = OdacDevice::query()->where('odac_id', $id)->first();
        if ($device === null) {
            $alias = OdacAlias::query()->where('path', $id)->value('odac_id');
            if ($alias !== null) {
                return $this->publicPage(redirect('/'.$alias, 301));
            }
            abort(404);
        }
        // A replaced sheet keeps its address, which now points to its successor.
        if ($device->superseded_by !== null) {
            return $this->publicPage(redirect('/'.$device->superseded_by, 301));
        }

        // The sheet only changes with the snapshot: ETag from stored values,
        // decided BEFORE the sheet is built.
        $etag = substr(hash('sha256', implode('|', [
            $snapshot?->fingerprint, $id, Inertia::getVersion(), $request->header('X-Inertia') ? 'json' : 'html',
        ])), 0, 32);
        $probe = new Response;
        $probe->setEtag($etag);
        $this->publicPage($probe);
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        $response = Inertia::render('Devices/Show', [
            'sheet' => $sheet($device),
            'snapshot' => $snapshot ? ['version' => $snapshot->version, 'fingerprint' => $snapshot->fingerprint] : null,
        ])->toResponse($request);
        $response->setEtag($etag);

        return $this->publicPage($response);
    }

    private function publicPage(Response $response): Response
    {
        $response->headers->set('Cache-Control', (string) config('odac.cache.pages'));
        $response->setVary('X-Inertia', false);

        return $response;
    }
}
