<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Controllers;

use App\Actions\Odac\BuildDeviceSheet;
use App\Actions\Odac\CatalogueStats;
use App\Models\OdacDevice;
use App\Models\OdacSnapshot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every reading page besides the device list and the sheet. All of them are
 * read-only views of the current projection; none reaches the network.
 */
class PageController extends Controller
{
    public const COMPARE_MAX = 4;

    public function __construct(private readonly CatalogueStats $stats) {}

    public function home(Request $request): Response
    {
        // The device list used to live at `/?q=…`: those links keep working.
        if (array_intersect(array_keys($request->query()), DeviceController::FILTERS) !== []) {
            $query = (string) $request->server('QUERY_STRING');

            return $this->publicPage(redirect('/devices'.($query !== '' ? '?'.$query : ''), 301));
        }

        return $this->snapshotPage($request, function () {
            $stats = $this->figures();

            return Inertia::render('Home', [
                'totals' => $stats['totals'],
                'contributors' => array_slice($stats['contributors'], 0, 5),
                'sources' => array_slice($stats['sources'], 0, 5),
            ]);
        });
    }

    public function brands(Request $request): Response
    {
        return $this->snapshotPage($request, fn () => Inertia::render('Brands', [
            'brands' => $this->figures()['by_brand'],
        ]));
    }

    public function chips(Request $request): Response
    {
        return $this->snapshotPage($request, function () {
            $stats = $this->figures();

            return Inertia::render('Chips', [
                'families' => $stats['families'],
                'unassigned' => collect($stats['by_family'])
                    ->reject(fn ($row) => $row['name'] !== null)
                    ->values(),
            ]);
        });
    }

    public function stats(Request $request): Response
    {
        return $this->snapshotPage($request, function () {
            $stats = $this->figures();

            return Inertia::render('Stats', [
                'totals' => $stats['totals'],
                'by_kind' => $stats['by_kind'],
                'by_brand' => array_slice($stats['by_brand'], 0, 15),
                'by_family' => $stats['by_family'],
                'families' => collect($stats['families'])->pluck('name', 'id'),
                'by_level' => $stats['by_level'],
                'identity' => $stats['identity'],
                'contributors' => $stats['contributors'],
                'sources' => array_slice($stats['sources'], 0, 15),
            ]);
        });
    }

    public function health(Request $request): Response
    {
        return $this->snapshotPage($request, function () {
            $stats = $this->figures();

            return Inertia::render('Health', [
                'totals' => $stats['totals'],
                'levels' => $stats['levels'],
                'identity' => $stats['identity'],
                'by_level' => $stats['by_level'],
                'health' => $stats['health'],
            ]);
        });
    }

    public function compare(Request $request, BuildDeviceSheet $sheet): Response
    {
        $ids = $request->query('d', []);
        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);
        $ids = array_slice(array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== ''))), 0, self::COMPARE_MAX);

        return $this->snapshotPage($request, function () use ($ids, $sheet) {
            $devices = OdacDevice::query()->whereIn('odac_id', $ids)->whereNull('superseded_by')->get()->keyBy('odac_id');
            $sheets = [];
            foreach ($ids as $id) {
                if (isset($devices[$id])) {
                    $sheets[] = $sheet($devices[$id]);
                }
            }

            return Inertia::render('Compare', [
                'sheets' => $sheets,
                'max' => self::COMPARE_MAX,
                'options' => OdacDevice::query()
                    ->select(['odac_id', 'brand_name', 'model_name'])
                    ->whereNull('superseded_by')
                    ->orderBy('brand_name')->orderBy('model_name')
                    ->get()
                    ->map(fn ($d) => ['id' => $d->odac_id, 'label' => $d->brand_name.' '.$d->model_name]),
                'family_names' => collect($this->figures()['families'])->pluck('name', 'id'),
            ]);
        });
    }

    public function contribute(Request $request): Response
    {
        return $this->snapshotPage($request, fn () => Inertia::render('Contribute', [
            'missing' => array_slice($this->figures()['health']['missing'], 0, 6),
        ]));
    }

    public function help(Request $request): Response
    {
        return $this->snapshotPage($request, fn () => Inertia::render('Help'));
    }

    private function figures(): array
    {
        return ($this->stats)(OdacSnapshot::current());
    }
}
