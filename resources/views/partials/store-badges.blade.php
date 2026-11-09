{{--
    Where to get the app. The badges are the stores' own artwork, untouched and at the same height, as their
    guidelines ask: Google Play's in the page's language, Apple's in English. The App Store's says coming soon until
    config('hooli.stores.ios') has an address; then it links there like the other.
--}}
@php
    $stores = config('hooli.stores');
    $locale = app()->getLocale();
@endphp
<ul class="flex flex-wrap items-center gap-x-3 gap-y-6" aria-label="{{ __('Get the app') }}">
    <li>
        <a href="{{ $stores['android'] }}" class="block rounded-[9px]">
            <img src="{{ asset("images/badges/google-play-$locale.png") }}" alt="{{ __('Get it on Google Play') }}" width="161" height="48" class="h-12 w-auto">
        </a>
    </li>
    <li>
        @if ($stores['ios'])
            <a href="{{ $stores['ios'] }}" class="block rounded-[9px]">
                <img src="{{ asset('images/badges/app-store.svg') }}" alt="{{ __('Download on the App Store') }}" width="144" height="48" class="h-12 w-auto">
            </a>
        @else
            <span class="relative block">
                <img src="{{ asset('images/badges/app-store.svg') }}" alt="{{ __('App Store') }}" width="144" height="48" class="h-12 w-auto">
                <span class="tag tag-know absolute -top-5 -right-2 text-[12px] shadow-[0_0_0_2px_var(--color-mist)]">{{ __('Coming soon') }}</span>
            </span>
        @endif
    </li>
</ul>
