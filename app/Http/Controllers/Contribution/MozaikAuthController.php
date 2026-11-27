<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Controllers\Contribution;

use App\Http\Controllers\Controller;
use App\Support\Odac\MozaikToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sign-in with a Mozaik Labs account. Lives in the `contribution` middleware
 * group: the only routes of this site with a session.
 */
class MozaikAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_if((string) config('odac.mozaik.secret') === '', 404);
        $state = Str::random(40);
        $request->session()->put('mozaik_state', $state);
        $request->session()->put('mozaik_return', self::safeReturn((string) $request->query('return', '/proposer')));

        return redirect()->away((string) config('odac.mozaik.connect_url').'?'.http_build_query([
            'return' => route('mozaik.callback'),
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->session()->pull('mozaik_state', '');
        $user = MozaikToken::verify((string) $request->query('token', ''), (string) config('odac.mozaik.secret'), $state);
        $return = (string) $request->session()->pull('mozaik_return', '/proposer');
        if ($user === null) {
            return redirect($return)->with('mozaik_error', true);
        }
        $request->session()->regenerate();
        $request->session()->put('mozaik_user', $user);

        return redirect($return);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('mozaik_user');
        $request->session()->regenerate();

        return redirect('/proposer');
    }

    /** Only a path on this site: never an open redirect. */
    public static function safeReturn(string $return): string
    {
        return preg_match('#^/(?!/)[^\s\\\\]*$#', $return) ? $return : '/proposer';
    }
}
