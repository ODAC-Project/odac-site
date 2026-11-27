<?php

// SPDX-License-Identifier: Apache-2.0

use App\Http\Controllers\Contribution\MozaikAuthController;
use App\Jobs\ForwardProposalToGitHub;
use App\Models\OdacProposal;
use App\Support\Odac\MozaikToken;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();
    config(['odac.mozaik.secret' => 'secret-de-test', 'odac.proposals.github_token' => '']);
});

function odac_signed_in(): array
{
    return ['mozaik_user' => ['sub' => '42', 'name' => 'Rémi']];
}

it('accepts a token signed with the secret, for the state it was issued for', function () {
    $token = MozaikToken::sign(['sub' => 42, 'name' => 'Rémi', 'state' => 'abc', 'exp' => time() + 60], 's');
    expect(MozaikToken::verify($token, 's', 'abc'))->toBe(['sub' => '42', 'name' => 'Rémi']);
});

it('refuses a forged, expired, replayed or long-lived token', function () {
    $ok = ['sub' => 1, 'name' => 'x', 'state' => 'abc', 'exp' => time() + 60];
    expect(MozaikToken::verify(MozaikToken::sign($ok, 'autre'), 's', 'abc'))->toBeNull()
        ->and(MozaikToken::verify(MozaikToken::sign(['exp' => time() - 1] + $ok, 's'), 's', 'abc'))->toBeNull()
        ->and(MozaikToken::verify(MozaikToken::sign($ok, 's'), 's', 'autre-etat'))->toBeNull()
        ->and(MozaikToken::verify(MozaikToken::sign(['exp' => time() + 3600] + $ok, 's'), 's', 'abc'))->toBeNull()
        ->and(MozaikToken::verify(MozaikToken::sign($ok, ''), '', 'abc'))->toBeNull()
        ->and(MozaikToken::verify('pas.un.jeton', 's', 'abc'))->toBeNull();
});

it('signs in through Mozaik Labs and returns to the page the person came from', function () {
    $go = $this->get('/connexion/mozaiklabs?return='.urlencode('/proposer?device=atelier-demo/convertisseur-un'));
    $go->assertRedirect();
    parse_str(parse_url($go->headers->get('Location'), PHP_URL_QUERY), $q);
    expect($q['return'])->toEndWith('/connexion/mozaiklabs/retour');

    $token = MozaikToken::sign(['sub' => 'u7', 'name' => 'Rémi', 'state' => $q['state'], 'exp' => time() + 60], 'secret-de-test');
    $this->get('/connexion/mozaiklabs/retour?token='.$token)
        ->assertRedirect('/proposer?device=atelier-demo/convertisseur-un')
        ->assertSessionHas('mozaik_user', ['sub' => 'u7', 'name' => 'Rémi']);
});

it('never redirects off the site after sign-in', function (string $return) {
    expect(MozaikAuthController::safeReturn($return))->toBe('/proposer');
})->with(['https://ailleurs.example', '//ailleurs.example', '/\\ailleurs.example', 'proposer']);

it('offers no Mozaik Labs sign-in while the secret is not set', function () {
    config(['odac.mozaik.secret' => '']);
    $this->get('/connexion/mozaiklabs')->assertNotFound();
    $this->get('/proposer')->assertInertia(fn (Assert $page) => $page->component('Propose')->where('signin_enabled', false));
});

it('prefills the form from the sheet and is never cached', function () {
    $this->get('/proposer?device=atelier-demo/convertisseur-un&field=dsd_max')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page->component('Propose')
            ->where('kind', 'change')
            ->where('device.odac_id', 'atelier-demo/convertisseur-un')
            ->where('field', 'dsd_max')
            ->where('user', null));
});

it('refuses a proposal without a Mozaik Labs sign-in', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/proposer', ['kind' => 'comment', 'odac_id' => 'atelier-demo/convertisseur-un', 'message' => 'Un commentaire assez long', 'pseudonym' => 'x', 'licence' => '1'])
        ->assertForbidden();
    expect(OdacProposal::count())->toBe(0);
});

it('records a proposal from a signed-in member, with the licence accepted', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withSession(odac_signed_in())
        ->post('/proposer', [
            'kind' => 'change', 'odac_id' => 'atelier-demo/convertisseur-un', 'field' => 'dsd_max',
            'message' => 'Le manuel donne DSD512, pas DSD256.', 'source_url' => 'https://exemple.test/manuel.pdf',
            'pseudonym' => 'Rémi', 'licence' => '1',
        ])
        ->assertRedirect('/proposer?device='.urlencode('atelier-demo/convertisseur-un'))
        ->assertSessionHas('proposal_sent');

    $p = OdacProposal::sole();
    expect($p->author_id)->toBe('42')->and($p->author_pseudonym)->toBe('Rémi')->and($p->field)->toBe('dsd_max')->and($p->licence_agreed_at)->not->toBeNull();
});

it('checks the proposal before recording it', function (array $bad, string $error) {
    $base = ['kind' => 'change', 'odac_id' => 'atelier-demo/convertisseur-un', 'message' => 'Un message assez long', 'pseudonym' => 'R', 'licence' => '1'];
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withSession(odac_signed_in())
        ->post('/proposer', array_merge($base, $bad))
        ->assertSessionHasErrors($error);
    expect(OdacProposal::count())->toBe(0);
})->with([
    'no licence' => [['licence' => null], 'licence'],
    'unknown device' => [['odac_id' => 'nulle/part'], 'odac_id'],
    'new device without name' => [['kind' => 'new_device', 'odac_id' => null], 'device_name'],
    'change without device' => [['odac_id' => null], 'odac_id'],
    'not a web address' => [['source_url' => 'javascript:alert(1)'], 'source_url'],
    'too short' => [['message' => 'court'], 'message'],
]);

it('forwards a proposal to the BDD as an issue, under the pseudonym only', function () {
    config(['odac.proposals.github_token' => 't']);
    Http::fake(['api.github.com/*' => Http::response(['html_url' => 'https://github.com/ODAC-Project/ODAC/issues/9'], 201)]);
    $p = OdacProposal::create([
        'kind' => 'comment', 'odac_id' => 'atelier-demo/convertisseur-un', 'message' => "Bonjour @quelquun\nligne deux",
        'author_id' => '42', 'author_pseudonym' => 'Rémi', 'licence_agreed_at' => now(),
    ]);
    (new ForwardProposalToGitHub($p->id))->handle();

    Http::assertSent(function ($request) {
        $body = $request['body'];

        return str_ends_with($request->url(), '/repos/ODAC-Project/ODAC/issues')
            && str_contains($request['title'], 'atelier-demo/convertisseur-un')
            && str_contains($body, '**Rémi**')
            && ! str_contains($body, '42')
            && ! str_contains($body, '@quelquun')   // no mention that pings someone
            && str_contains($body, 'CC BY-SA 4.0');
    });
    expect($p->fresh()->github_issue_url)->toBe('https://github.com/ODAC-Project/ODAC/issues/9');
});

it('keeps the reading pages free of session and cookies', function () {
    $this->get('/proposer'); // a session exists on the contribution side…
    foreach (['/', '/devices', '/pourquoi', '/atelier-demo/convertisseur-un'] as $url) {
        $response = $this->get($url)->assertOk();
        expect($response->headers->getCookies())->toBe([], "{$url} sets a cookie");
    }
});

it('explains the levels on the why page', function () {
    $this->get('/pourquoi')->assertInertia(fn (Assert $page) => $page->component('Why')->has('figures.levels')->has('figures.identity'));
});
