<?php

// SPDX-License-Identifier: Apache-2.0

use App\Http\Controllers\Contribution\MozaikAuthController;
use App\Http\Controllers\Contribution\ProposalController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MirrorController;
use App\Http\Controllers\PageController;
use App\Support\Odac\Pointer;
use Illuminate\Support\Facades\Route;

// ── The mirror (delivery 1): what Tune and any other consumer reads ──────
Route::get('/latest.json', [MirrorController::class, 'latest'])->name('mirror.latest');
Route::get('/artifacts/{name}', [MirrorController::class, 'artifact'])
    ->where('name', Pointer::FILENAME_PATTERN)
    ->name('mirror.artifact');

// ── Public reading (deliveries 2 to 4) ───────────────────────────────────
// One-segment addresses: they can never collide with a sheet (two segments).
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
Route::get('/brands', [PageController::class, 'brands'])->name('brands');
Route::get('/chips', [PageController::class, 'chips'])->name('chips');
Route::get('/compare', [PageController::class, 'compare'])->name('compare');
Route::get('/stats', [PageController::class, 'stats'])->name('stats');
Route::get('/health', [PageController::class, 'health'])->name('health');
Route::get('/contribute', [PageController::class, 'contribute'])->name('contribute');
Route::get('/help', [PageController::class, 'help'])->name('help');
Route::get('/pourquoi', [PageController::class, 'why'])->name('why');

// ── Contribution (session, cookies, CSRF: the only routes that have them) ─
// Kept apart so every public page above stays session-less and cacheable.
Route::middleware('contribution')->group(function () {
    Route::get('/proposer', [ProposalController::class, 'create'])->name('proposals.create');
    Route::post('/proposer', [ProposalController::class, 'store'])->middleware('throttle:10,60')->name('proposals.store');
    Route::get('/connexion/mozaiklabs', [MozaikAuthController::class, 'redirect'])->name('mozaik.redirect');
    Route::get('/connexion/mozaiklabs/retour', [MozaikAuthController::class, 'callback'])->name('mozaik.callback');
    Route::post('/deconnexion', [MozaikAuthController::class, 'logout'])->name('mozaik.logout');
});

// Sheet address: /<brand>/<model>, frozen once announced. Reserved first
// segments can never be a brand.
Route::get('/{brand}/{model}', [DeviceController::class, 'show'])
    ->where('brand', '(?!(?:artifacts|api|dist|odac|build|storage|up|proposer|connexion|deconnexion|pourquoi)$)[a-z0-9][a-z0-9._-]*')
    ->where('model', '[a-z0-9][a-z0-9._-]*')
    ->name('devices.show');
