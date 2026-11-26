<?php

// SPDX-License-Identifier: Apache-2.0

use Illuminate\Support\Facades\Schedule;

// Reads latest.json (a few hundred bytes); downloads only on a new fingerprint.
Schedule::command('odac:ingest')->everyTenMinutes()->withoutOverlapping(15);
