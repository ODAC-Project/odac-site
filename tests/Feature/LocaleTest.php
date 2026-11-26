<?php

// SPDX-License-Identifier: Apache-2.0

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->dist = odac_publish(odac_tmp_dir('dist'), odac_demo_jsonl());
    $this->artisan('odac:ingest', ['--source' => $this->dist['dir']])->assertSuccessful();
});

it('follows Accept-Language: French for French, English for anything else', function (string $header, string $locale) {
    $response = $this->get('/', $header === '' ? [] : ['Accept-Language' => $header]);

    $response->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
    expect($response->getContent())->toContain('<html lang="'.$locale.'">');
})->with([
    'French' => ['fr-FR,fr;q=0.9,en;q=0.8', 'fr'],
    'French, bare' => ['fr', 'fr'],
    'English' => ['en-GB,en;q=0.9', 'en'],
    'German' => ['de-DE,de;q=0.9', 'en'],
    'French only second' => ['de,fr;q=0.8', 'en'],
    'French weighted first' => ['en;q=0.5,fr-CA', 'fr'],
    'no header' => ['', 'en'],
]);

it('lets the preference cookie override Accept-Language, and ?lang override both', function () {
    $this->withUnencryptedCookie('odac_lang', 'fr')
        ->get('/', ['Accept-Language' => 'en'])
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));

    $this->withUnencryptedCookie('odac_lang', 'fr')
        ->get('/?lang=en', ['Accept-Language' => 'fr'])
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

    // An unknown value is ignored.
    $this->withUnencryptedCookie('odac_lang', 'de')
        ->get('/?lang=xx', ['Accept-Language' => 'fr'])
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
});

it('never sets a cookie itself, and varies the page on language', function () {
    $response = $this->get('/?lang=fr');

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Vary'))->toBe('X-Inertia, Accept-Language, Cookie');
});

it('carries hreflang links (fr, en, x-default = en) and the right meta description', function () {
    $html = $this->get('/devices?kind=dac', ['Accept-Language' => 'fr'])->getContent();

    expect($html)
        ->toContain('hreflang="fr" href="http://localhost/devices?kind=dac&amp;lang=fr"')
        ->toContain('hreflang="en" href="http://localhost/devices?kind=dac&amp;lang=en"')
        ->toContain('hreflang="x-default" href="http://localhost/devices?kind=dac&amp;lang=en"')
        ->toContain('le registre ouvert, tenu par la communauté');

    expect($this->get('/', ['Accept-Language' => 'en'])->getContent())->toContain('the open, community-maintained registry');
});

it('translates the 404 page', function () {
    $this->get('/atelier-demo/inconnu', ['Accept-Language' => 'fr'])->assertNotFound()->assertSee('Page introuvable');
    $this->get('/atelier-demo/inconnu', ['Accept-Language' => 'en'])->assertNotFound()->assertSee('Page not found');
});

it('leaves the mirror untouched by language', function () {
    $response = $this->get('/latest.json', ['Accept-Language' => 'fr']);

    $response->assertOk();
    expect((string) $response->headers->get('Vary'))->not->toContain('Accept-Language')->not->toContain('Cookie');
    expect($response->json('fingerprint'))->toBe($this->dist['fingerprint']);
});
