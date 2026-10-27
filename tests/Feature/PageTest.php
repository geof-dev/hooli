<?php

it('shows the landing page with every section the brief asks for', function () {
    $this->get('/')->assertOk()
        ->assertSee('Care for your hormones, one scan at a time.')
        ->assertSee('The answers exist. They’re just hard to find.')
        ->assertSee('How Hooli works')
        ->assertSee('Check your medication')
        ->assertSee('Clear answers to everyday questions')
        ->assertSee('Why I’m building Hooli')
        ->assertSee('Be among the first to use Hooli')
        ->assertSee('Questions and answers');
});

it('carries the required disclaimer, word for word', function () {
    $this->get('/')->assertSeeText('Hooli provides information, not medical advice. It does not replace diagnosis or treatment by a health professional. Always talk to your doctor before changing a medication.');
});

it('sets no cookies', function () {
    $this->get('/')->assertOk()->assertHeaderMissing('Set-Cookie');
});

it('describes itself to search engines and link previews', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toContain('<link rel="canonical" href="'.url('/').'">')
        ->toContain('<meta property="og:image" content="'.asset('images/og.jpg').'">')
        ->toContain('"@type":"FAQPage"')
        ->toContain('"name":"Is Hooli medical advice?"');
    expect(public_path('images/og.jpg'))->toBeFile()
        ->and(public_path('favicon.svg'))->toBeFile()
        ->and(public_path('apple-touch-icon.png'))->toBeFile();
});

it('offers every situation in the waitlist form', function () {
    $response = $this->get('/');
    foreach (config('hooli.situations') as $value => $label) {
        $response->assertSee('<option value="'.$value.'">'.e($label).'</option>', false);
    }
});
