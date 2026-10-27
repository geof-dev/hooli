{{--
    The waitlist form. Works as a plain form; app.js sends it in the background and swaps in the thanks. Without
    JavaScript the server sends the visitor back to the home page with ?waitlist=joined or ?waitlist=invalid.
    The short one, in the hero, asks only the email, on a light ground; the full one, on the pine band, also asks
    the situation. $id keeps their fields apart when a page has both.
--}}
@php
    $state = request('waitlist');
    $short ??= false;
    $id ??= 'waitlist';
@endphp
<div class="grid gap-4" data-waitlist>
    <form method="post" action="{{ localized_route('waitlist.join') }}" class="grid gap-4" novalidate @if ($state === 'joined') hidden @endif>
        <input type="hidden" name="utm_source" value="{{ \Illuminate\Support\Str::limit((string) request('utm_source'), 80, '') }}">
        <input type="hidden" name="utm_campaign" value="{{ \Illuminate\Support\Str::limit((string) request('utm_campaign'), 80, '') }}">
        {{-- Left empty by people, who never see it; filled by bots, which see every field. --}}
        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        @if ($short)
            <div class="grid gap-2">
                <label for="{{ $id }}-email" class="sr-only">{{ __('Your email address') }}</label>
                <div class="flex flex-wrap gap-1.5 rounded-[26px] bg-cream p-1.5 shadow-[0_0_0_1px_var(--color-line),0_14px_30px_-18px_rgb(42_64_51/.35)] focus-within:shadow-[0_0_0_2px_var(--color-sage-deep)] sm:flex-nowrap sm:rounded-full">
                    <input id="{{ $id }}-email" name="email" type="email" required autocomplete="email" inputmode="email" spellcheck="false"
                           placeholder="{{ __('you@example.com') }}" class="min-w-0 flex-1 basis-full bg-transparent px-4 py-3 text-[17px] text-ink placeholder:text-ink-2/70 focus-visible:outline-none sm:basis-auto"
                           aria-describedby="{{ $id }}-email-error" @if ($state === 'invalid') aria-invalid="true" @endif>
                    <button type="submit" class="btn w-full text-[17px] sm:w-auto">{{ __('Join the waitlist') }}</button>
                </div>
                <p id="{{ $id }}-email-error" role="alert" class="text-[16px] font-semibold text-clay" @unless ($state === 'invalid') hidden @endunless>{{ __('Enter an email address like name@example.com.') }}</p>
            </div>
        @else
            <div class="grid gap-2">
                <label for="{{ $id }}-email" class="font-semibold">{{ __('Your email address') }}</label>
                <input id="{{ $id }}-email" name="email" type="email" required autocomplete="email" inputmode="email" spellcheck="false"
                       placeholder="{{ __('you@example.com') }}" class="field text-[17px]"
                       aria-describedby="{{ $id }}-email-error" @if ($state === 'invalid') aria-invalid="true" @endif>
                <p id="{{ $id }}-email-error" role="alert" class="text-[16px] font-semibold text-blush" @unless ($state === 'invalid') hidden @endunless>{{ __('Enter an email address like name@example.com.') }}</p>
            </div>

            <div class="grid gap-2">
                <label for="{{ $id }}-situation" class="font-semibold">{{ __('Your situation') }} <span class="font-normal text-on-pine-2">{{ __('(optional)') }}</span></label>
                <select id="{{ $id }}-situation" name="situation" class="field text-[17px]" aria-describedby="{{ $id }}-situation-note">
                    <option value="">{{ __('Choose one, if you like') }}</option>
                    @foreach (config('hooli.situations') as $value => $label)
                        <option value="{{ $value }}">{{ __($label) }}</option>
                    @endforeach
                </select>
                <p id="{{ $id }}-situation-note" class="text-[15px] leading-snug text-on-pine-2">{{ __('It helps us decide what to build first. We keep it with your email, never share it, and delete both whenever you ask.') }}</p>
            </div>

            <div><button type="submit" class="btn text-[17px]">{{ __('Join the waitlist') }}</button></div>
        @endif
    </form>

    <div data-waitlist-done tabindex="-1" class="grid gap-2 outline-none" @unless ($state === 'joined') hidden @endunless>
        <p class="font-display {{ $short ? 'text-[30px]' : 'text-[34px]' }} leading-tight">{{ __('You’re on the list.') }}</p>
        <p class="max-w-[44ch] {{ $short ? 'text-ink-2' : 'text-on-pine-2' }}">{{ __('We’ll write once, the day Hooli opens. Until then, hooli endast: take care of yourself.') }}</p>
    </div>
</div>
