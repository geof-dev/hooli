<?php

use App\Guide;

it('has every guide in every language', function () {
    foreach (array_keys(config('hooli.locales')) as $locale) {
        foreach (config('hooli.guides') as $key) {
            expect(resource_path("guides/$locale/$key.md"))->toBeFile();
        }
    }
});

it('shows a guide with its key points, its sections and the structured data search engines read', function () {
    $guide = Guide::get('progestins-meningioma', 'fr');

    $html = $this->get('/fr/guides/progestatifs-meningiome')->assertOk()
        ->assertSee('<h1 id="guide-title" class="max-w-[17em] text-[clamp(38px,5.2vw,68px)] leading-[1.04]">'.e($guide->title).'</h1>', false)
        ->assertSee($guide->points[0])
        ->assertSee('Hooli fournit des informations, pas des avis médicaux.')
        ->getContent();

    expect($guide->contents)->not->toBeEmpty();
    foreach (array_keys($guide->contents) as $id) {
        expect($html)->toContain('<h2 id="'.$id.'">')->toContain('href="#'.$id.'"');
    }
    expect($html)->toContain('<title>'.e($guide->title).' | Hooli</title>')
        ->toContain('<meta property="og:type" content="article">')
        ->toContain('"@type":"Article"')
        ->toContain('"@type":"BreadcrumbList"')
        ->toContain('<link rel="alternate" hreflang="en" href="'.url('/guides/progestins-meningioma').'">')
        ->toContain('<link rel="alternate" hreflang="et" href="'.url('/et/juhendid/progestiinid-meningeoom').'">');
});

it('leads only to pages that exist', function () {
    foreach (array_keys(config('hooli.locales')) as $locale) {
        foreach (Guide::all($locale) as $guide) {
            preg_match_all('/href="(\/[^"#]*)"/', $guide->html, $links);
            foreach ($links[1] as $link) {
                $this->get($link)->assertOk();
            }
        }
    }
});

it('lists the guides on the home page and on the guides page', function () {
    $this->get('/')->assertSee('Guides for the bigger questions')
        ->assertSee('href="'.url('/guides/endocrine-disruptors').'"', false);
    $this->get('/et/juhendid')->assertOk()
        ->assertSee(Guide::get('cosmetic-ingredients', 'et')->title);
});

it('answers a guide that doesn’t exist, or isn’t in that language, with a 404', function () {
    $this->get('/guides/nothing-here')->assertNotFound();
    $this->get('/guides/perturbateurs-endocriniens')->assertNotFound();
});

it('gives search engines a sitemap of every page in every language', function () {
    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
    $xml = simplexml_load_string($response->getContent());

    expect($xml->url)->toHaveCount(3 * (2 + count(config('hooli.guides'))));
    expect($response->getContent())
        ->toContain('<loc>'.url('/et/juhendid/hormoonhairijad').'</loc>')
        ->toContain('<xhtml:link rel="alternate" hreflang="fr" href="'.url('/fr/guides/perturbateurs-endocriniens').'"/>');
    expect(file_get_contents(public_path('robots.txt')))->toContain('Sitemap: https://gethooli.app/sitemap.xml');
});
