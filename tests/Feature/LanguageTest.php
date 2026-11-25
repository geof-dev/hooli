<?php

use App\Guide;

it('speaks French at /fr and Estonian at /et', function () {
    $this->get('/fr')->assertOk()
        ->assertSee('<html lang="fr">', false)
        ->assertSee('Prenez soin de vos hormones, un scan à la fois.')
        ->assertSee('alt="Télécharger dans l’App Store"', false);

    $this->get('/et')->assertOk()
        ->assertSee('<html lang="et">', false)
        ->assertSee("Hooli oma hormoonidest\u{a0}– skaneeri ja saa aru.")
        ->assertSee('href="'.route('et.guides').'"', false);
});

it('links every page to its translations, and calls English the default', function () {
    $html = $this->get('/fr/guides')->getContent();

    expect($html)->toContain('<link rel="canonical" href="'.url('/fr/guides').'">')
        ->toContain('<link rel="alternate" hreflang="en" href="'.url('/guides').'">')
        ->toContain('<link rel="alternate" hreflang="fr" href="'.url('/fr/guides').'">')
        ->toContain('<link rel="alternate" hreflang="et" href="'.url('/et/juhendid').'">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="'.url('/guides').'">')
        ->toContain('<meta property="og:locale" content="fr_FR">')
        ->toContain('<meta property="og:image" content="'.asset('images/og-fr.jpg').'">');
    expect(public_path('images/og-fr.jpg'))->toBeFile()
        ->and(public_path('images/og-et.jpg'))->toBeFile();
});

it('has every word of every page in French and Estonian', function () {
    $missing = [];
    app('translator')->handleMissingKeysUsing(function (string $key, array $replace, ?string $locale) use (&$missing) {
        // English needs no file: its words are the keys.
        if ($locale !== 'en') {
            $missing[] = "$locale: $key";
        }
    });

    foreach (['fr', 'et', 'en'] as $locale) {
        $this->get(localized_route('home', locale: $locale))->assertOk();
        $this->get(localized_route('guides', locale: $locale))->assertOk();
        Guide::all($locale)->each(fn (Guide $guide) => $this->get($guide->url())->assertOk());
    }

    expect(array_unique($missing))->toBe([]);
});
