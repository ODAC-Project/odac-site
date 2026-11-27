<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Http\Controllers\Contribution;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardProposalToGitHub;
use App\Models\OdacDevice;
use App\Models\OdacProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Propose a new device, a correction or a comment, in free text. Anyone sees
 * the page; sending it needs a Mozaik Labs sign-in. Without one, the page
 * offers the same proposal as a pre-filled GitHub issue.
 */
class ProposalController extends Controller
{
    public function create(Request $request): SymfonyResponse
    {
        $device = null;
        if (is_string($id = $request->query('device')) && $id !== '') {
            $row = OdacDevice::query()->where('odac_id', $id)->first();
            $device = $row ? ['odac_id' => $row->odac_id, 'brand' => $row->brand_name, 'model' => $row->model_name] : null;
        }
        $kind = in_array($request->query('type'), OdacProposal::KINDS, true) ? $request->query('type') : ($device ? 'change' : 'new_device');
        $field = is_string($request->query('field')) && preg_match('/^[a-z0-9_.]{1,64}$/', $request->query('field')) ? $request->query('field') : null;

        $response = Inertia::render('Propose', [
            'kind' => $kind,
            'device' => $device,
            'field' => $field,
            'user' => $request->session()->get('mozaik_user'),
            'signin_enabled' => (string) config('odac.mozaik.secret') !== '',
            'signin_failed' => (bool) $request->session()->get('mozaik_error'),
            'sent' => $request->session()->get('proposal_sent'),
        ])->toResponse($request);
        // Per person (who is signed in, what was sent): never in a shared cache.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->session()->get('mozaik_user');
        abort_unless(is_array($user) && isset($user['sub']), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(OdacProposal::KINDS)],
            'odac_id' => ['nullable', 'string', 'max:191', Rule::exists('odac_devices', 'odac_id')],
            'field' => ['nullable', 'string', 'regex:/^[a-z0-9_.]{1,64}$/'],
            'device_name' => ['nullable', 'required_if:kind,new_device', 'string', 'max:191'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'pseudonym' => ['required', 'string', 'max:64'],
            'licence' => ['accepted'],
        ]);
        if ($data['kind'] !== 'new_device' && empty($data['odac_id'])) {
            return back()->withErrors(['odac_id' => 'required'])->withInput();
        }

        $proposal = OdacProposal::create([
            'kind' => $data['kind'],
            'odac_id' => $data['odac_id'] ?? null,
            'field' => $data['field'] ?? null,
            'device_name' => $data['device_name'] ?? null,
            'message' => trim($data['message']),
            'source_url' => $data['source_url'] ?? null,
            'author_id' => (string) $user['sub'],
            'author_pseudonym' => trim($data['pseudonym']),
            'licence_agreed_at' => now(),
        ]);
        ForwardProposalToGitHub::dispatch($proposal->id)->afterResponse();

        return redirect('/proposer'.($proposal->odac_id ? '?device='.urlencode($proposal->odac_id) : ''))
            ->with('proposal_sent', $proposal->id);
    }
}
