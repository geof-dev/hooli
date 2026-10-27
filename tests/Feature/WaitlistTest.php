<?php

use App\Models\Subscriber;

function joinWaitlist(array $fields = [], bool $json = true)
{
    $fields += ['email' => 'Kadri.Tamm@Example.com', 'situation' => 'thyroid'];

    return $json ? test()->postJson('/waitlist', $fields) : test()->post('/waitlist', $fields);
}

it('adds an address to the list, with the situation and the campaign', function () {
    joinWaitlist(['utm_source' => 'instagram', 'utm_campaign' => 'launch'])->assertOk()->assertJson(['ok' => true]);

    $subscriber = Subscriber::sole();
    expect($subscriber->email)->toBe('kadri.tamm@example.com')
        ->and($subscriber->locale)->toBe('en')
        ->and($subscriber->situation)->toBe('thyroid')
        ->and($subscriber->utm_source)->toBe('instagram')
        ->and($subscriber->utm_campaign)->toBe('launch');
});

it('treats the situation as optional', function () {
    joinWaitlist(['situation' => ''])->assertOk();
    expect(Subscriber::sole()->situation)->toBeNull();
});

it('works as a plain form, landing back on the form with the thanks', function () {
    joinWaitlist(json: false)->assertRedirect(route('home').'?waitlist=joined#waitlist');
    expect(Subscriber::count())->toBe(1);

    $html = $this->get('/?waitlist=joined')->assertOk()->getContent();
    expect($html)->toMatch('/<form [^>]*\bhidden\b[^>]*>/')
        ->toMatch('/<div data-waitlist-done (?![^>]*\bhidden\b)[^>]*>/');
});

it('refuses an address that isn’t one', function () {
    joinWaitlist(['email' => 'kadri'])->assertStatus(422)->assertJson(['message' => 'Enter an email address like name@example.com.']);
    joinWaitlist(['email' => 'kadri'], json: false)->assertRedirect(route('home').'?waitlist=invalid#waitlist');
    expect(Subscriber::count())->toBe(0);

    $this->get('/?waitlist=invalid')->assertSee('aria-invalid="true"', false);
});

it('refuses a situation that isn’t on the list', function () {
    joinWaitlist(['situation' => 'cancer'])->assertStatus(422);
    expect(Subscriber::count())->toBe(0);
});

it('keeps one row per address, and never changes someone’s answer', function () {
    joinWaitlist();
    joinWaitlist(['email' => 'kadri.tamm@example.com', 'situation' => 'menopause'])->assertOk()->assertJson(['ok' => true]);

    expect(Subscriber::count())->toBe(1)
        ->and(Subscriber::sole()->situation)->toBe('thyroid');
});

it('quietly ignores bots that fill the hidden field', function () {
    joinWaitlist(['website' => 'https://spam.example'])->assertOk()->assertJson(['ok' => true]);
    expect(Subscriber::count())->toBe(0);
});

it('slows down anyone sending the form over and over', function () {
    foreach (range(1, 8) as $i) {
        joinWaitlist(['email' => "person$i@example.com"])->assertOk();
    }
    joinWaitlist(['email' => 'person9@example.com'])->assertStatus(429);
});

it('sets no cookie when joining', function () {
    joinWaitlist()->assertHeaderMissing('Set-Cookie');
});

it('forgets someone on request', function () {
    joinWaitlist();
    $this->artisan('waitlist:forget', ['email' => 'KADRI.TAMM@example.com'])->assertSuccessful();
    expect(Subscriber::count())->toBe(0);
});
