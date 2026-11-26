<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Controllers;

use App\Actions\Odac\BuildDeviceSheet;
use App\Actions\Odac\CatalogueStats;
use App\Models\OdacAlias;
use App\Models\OdacDevice;
use App\Models\OdacSnapshot;
use App\Support\Odac\Completeness;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DeviceController extends Controller
{
    public const KINDS = ['dac', 'network_player'];

    /** Query parameters of the device list (the old `/?q=…` addresses carry them). */
    public const FILTERS = ['q', 'brand', 'kind', 'contested', 'doubt', 'level', 'family', 'page'];

    public function index(Request $request): Response
    {
        $level = $request->query('level');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'brand' => (string) $request->query('brand', ''),
            'kind' => in_array($request->query('kind'), self::KINDS, true) ? (string) $request->query('kind') : '',
            'contested' => $request->boolean('contested'),
            'doubt' => $request->boolean('doubt'),
            'level' => is_string($level) && preg_match('/^[0-3]$/', $level) ? $level : '',
            'family' => mb_substr((string) $request->query('family', ''), 0, 60),
        ];

        return $this->snapshotPage($request, function () use ($filters) {
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
            if ($filters['level'] !== '') {
                // "level 2" means "at least level 2": level 3 sits on top of it.
                $query->where('level', '>=', (int) $filters['level']);
            }
            if ($filters['family'] !== '') {
                $query->whereRaw("json_extract(data, '$.family') = ?", [$filters['family']]);
            }

            $devices = $query->orderBy('brand_name')->orderBy('model_name')
                ->paginate((int) config('odac.per_page'))
                ->withQueryString();

            $brands = OdacDevice::query()
                ->select(['brand_slug', 'brand_name'])
                ->whereNull('superseded_by')
                ->distinct()
                ->orderBy('brand_name')
                ->get();

            return Inertia::render('Devices/Index', [
                'devices' => $devices,
                'filters' => $filters,
                'brands' => $brands,
            ]);
        });
    }

    public function show(Request $request, string $brand, string $model, BuildDeviceSheet $sheet, CatalogueStats $stats): Response
    {
        $id = "{$brand}/{$model}";

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

        return $this->snapshotPage($request, function () use ($device, $sheet, $stats) {
            $figures = $stats(OdacSnapshot::current());
            $levels = $figures['levels']['fields'];

            return Inertia::render('Devices/Show', [
                'sheet' => $sheet($device),
                'family_names' => collect($figures['families'])->pluck('name', 'id'),
                'completeness' => $figures['levels']['known'] ? [
                    'level2_missing' => Completeness::missing($device->data, $levels[2] ?? []),
                    'level3_missing' => Completeness::missing($device->data, $levels[3] ?? []),
                ] : null,
            ]);
        });
    }
}
