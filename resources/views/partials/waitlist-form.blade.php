{{--
    The waitlist form, on the pine band. Works as a plain form; app.js sends it in the background and swaps in the
    thanks. Without JavaScript the server sends the visitor back here with ?waitlist=joined or ?waitlist=invalid.
--}}
@php
    $state = request('waitlist');
@endphp
<div class="grid gap-4" data-waitlist>
    <form method="post" action="{{ route('waitlist.join') }}" class="grid gap-4" novalidate @if ($state === 'joined') hidden @endif>
        <input type="hidden" name="utm_source" value="{{ \Illuminate\Support\Str::limit((string) request('utm_source'), 80, '') }}">
        <input type="hidden" name="utm_campaign" value="{{ \Illuminate\Support\Str::limit((string) request('utm_campaign'), 80, '') }}">
        {{-- Left empty by people, who never see it; filled by bots, which see every field. --}}
        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="grid gap-2">
            <label for="email" class="font-semibold">{{ __('Your email address') }}</label>
            <input id="email" name="email" type="email" required autocomplete="email" inputmode="email" spellcheck="false"
                   placeholder="{{ __('you@example.com') }}" class="field text-[17px]"
                   aria-describedby="email-error" @if ($state === 'invalid') aria-invalid="true" @endif>
            <p id="email-error" role="alert" class="text-[16px] font-semibold text-blush" @unless ($state === 'invalid') hidden @endunless>{{ __('Enter an email address like name@example.com.') }}</p>
        </div>

        <div class="grid gap-2">
            <label for="situation" class="font-semibold">{{ __('Your situation') }} <span class="font-normal text-on-pine-2">{{ __('(optional)') }}</span></label>
            <select id="situation" name="situation" class="field text-[17px]" aria-describedby="situation-note">
                <option value="">{{ __('Choose one, if you like') }}</option>
                @foreach (config('hooli.situations') as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </select>
            <p id="situation-note" class="text-[15px] leading-snug text-on-pine-2">{{ __('It helps us decide what to build first. We keep it with your email, never share it, and delete both whenever you ask.') }}</p>
        </div>

        <div><button type="submit" class="btn text-[17px]">{{ __('Join the waitlist') }}</button></div>
    </form>

    <div data-waitlist-done tabindex="-1" class="grid gap-3 outline-none" @unless ($state === 'joined') hidden @endunless>
        <p class="font-display text-[34px] leading-tight">{{ __('You’re on the list.') }}</p>
        <p class="max-w-[44ch] text-on-pine-2">{{ __('We’ll write once, the day Hooli opens. Until then, hooli endast: take care of yourself.') }}</p>
    </div>
</div>
