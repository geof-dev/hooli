{{-- The header's language menu: this page in each language. Works without JavaScript; app.js closes it on a click outside. --}}
<details class="relative" data-lang-menu>
    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-full px-3 py-2 font-semibold text-ink-2 hover:text-ink [&::-webkit-details-marker]:hidden">
        <svg viewBox="0 0 20 20" class="size-[18px]" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="7.5"/><path d="M2.5 10h15M10 2.5c2 2.2 3 4.7 3 7.5s-1 5.3-3 7.5c-2-2.2-3-4.7-3-7.5s1-5.3 3-7.5Z"/></svg>
        <span><span class="sr-only">{{ __('Language') }}: </span>{{ config('hooli.locales.'.app()->getLocale().'.name') }}</span>
    </summary>
    <ul class="absolute top-full right-0 z-20 mt-2 grid min-w-44 rounded-2xl bg-cream p-1.5 shadow-[0_0_0_1px_var(--color-line),0_20px_40px_-20px_rgb(42_64_51/.4)]">
        @foreach ($alternates as $code => $url)
            <li>
                <a href="{{ $url }}" hreflang="{{ $code }}" lang="{{ $code }}" class="flex items-center justify-between gap-4 rounded-xl px-3 py-2 hover:bg-mist" @if ($code === app()->getLocale()) aria-current="page" @endif>
                    {{ config("hooli.locales.$code.name") }}
                    @if ($code === app()->getLocale())
                        <svg viewBox="0 0 16 16" class="size-4 text-sage-deep" aria-hidden="true"><path d="m3 8.5 3.2 3L13 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</details>
