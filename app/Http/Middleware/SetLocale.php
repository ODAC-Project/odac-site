<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Middleware;

use App\Support\Odac\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locale::resolve($request));

        return $next($request);
    }
}
