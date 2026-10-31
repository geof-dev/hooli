<?php

it('shows the privacy policy at the address the app links to', function () {
    $this->get('/privacy')->assertOk()
        ->assertSee('<h1 id="privacy-title"', false)
        ->assertSeeText('Privacy policy')
        ->assertSeeText('your health profile and your scans stay on your phone')
        ->assertSeeText('Delete everything on this phone')
        ->assertSee('mailto:hello@gethooli.app', false)
        ->assertHeaderMissing('Set-Cookie');
});

it('has the policy in every language, each pointing to the others', function () {
    $this->get('/fr/confidentialite')->assertOk()->assertSeeText('Politique de confidentialité');
    $this->get('/et/privaatsus')->assertOk()->assertSeeText('Privaatsuspoliitika')
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/privacy').'">', false)
        ->assertSee('<link rel="alternate" hreflang="fr" href="'.url('/fr/confidentialite').'">', false);
});

it('links to the policy from every page’s footer', function () {
    $this->get('/')->assertSee('href="'.url('/privacy').'"', false);
    $this->get('/et')->assertSee('href="'.url('/et/privaatsus').'"', false)->assertSeeText('Privaatsuspoliitika');
});
