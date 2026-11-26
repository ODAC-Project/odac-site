<?php

// SPDX-License-Identifier: Apache-2.0

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MirrorController;
use App\Support\Odac\Pointer;
use Illuminate\Support\Facades\Route;

// ── The mirror (delivery 1): what Tune and any other consumer reads ──────
Route::get('/latest.json', [MirrorController::class, 'latest'])->name('mirror.latest');
Route::get('/artifacts/{name}', [MirrorController::class, 'artifact'])
    ->where('name', Pointer::FILENAME_PATTERN)
    ->name('mirror.artifact');

// ── Public reading (delivery 2) ──────────────────────────────────────────
Route::get('/', [DeviceController::class, 'index'])->name('devices.index');

// Sheet address: /<brand>/<model>, frozen once announced (TO CONFIRM by the
// project owner: design document 2 also writes /odac/<brand>/<model>). Reserved first
// segments can never be a brand.
Route::get('/{brand}/{model}', [DeviceController::class, 'show'])
    ->where('brand', '(?!(?:artifacts|api|dist|odac|build|storage|up)$)[a-z0-9][a-z0-9._-]*')
    ->where('model', '[a-z0-9][a-z0-9._-]*')
    ->name('devices.show');
