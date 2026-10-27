<?php

use App\Guide;
use App\Models\Subscriber;

it('speaks French at /fr and Estonian at /et', function () {
    $this->get('/fr')->assertOk()
        ->assertSee('<html lang="fr">', false)
        ->assertSee('Prenez soin de vos hormones, un scan à la fois.')
        ->assertSee('<form method="post" action="'.route('fr.waitlist.join').'"', false);

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
        // English needs no file: its words are the keys. The validator looks up messages of its own, which the
        // site never shows: the waitlist writes its own.
        if ($locale !== 'en' && ! str_starts_with($key, 'validation.')) {
            $missing[] = "$locale: $key";
        }
    });

    foreach (['fr', 'et', 'en'] as $locale) {
        $this->get(localized_route('home', locale: $locale))->assertOk();
        $this->get(localized_route('guides', locale: $locale))->assertOk();
        Guide::all($locale)->each(fn (Guide $guide) => $this->get($guide->url())->assertOk());
        $this->get(localized_route('home', locale: $locale).'?waitlist=joined')->assertOk();
        $this->postJson(localized_route('waitlist.join', locale: $locale), ['email' => 'kadri'])->assertStatus(422);
        $this->postJson(localized_route('waitlist.join', locale: $locale), ['email' => 'kadri@example.com', 'situation' => 'cancer'])->assertStatus(422);
    }

    expect(array_unique($missing))->toBe([]);
});

it('keeps the language someone joined in, and brings them back to it', function () {
    $this->post('/fr/waitlist', ['email' => 'Marie@Example.fr'])->assertRedirect(url('/fr').'?waitlist=joined#waitlist');
    $this->postJson('/et/waitlist', ['email' => 'kadri@example.ee'])->assertOk();

    expect(Subscriber::where('email', 'marie@example.fr')->value('locale'))->toBe('fr')
        ->and(Subscriber::where('email', 'kadri@example.ee')->value('locale'))->toBe('et');
});

it('tells the French a mistyped address in French', function () {
    $this->postJson('/fr/waitlist', ['email' => 'marie'])->assertStatus(422)
        ->assertJson(['message' => 'Saisissez une adresse e-mail du type nom@exemple.fr.']);
});
