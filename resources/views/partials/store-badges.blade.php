{{--
    Where to get the app. The badges are the stores' own artwork, untouched and at the same height, as their
    guidelines ask: Google Play's in the page's language, Apple's in English.
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
        <a href="{{ $stores['ios'] }}" class="block rounded-[9px]">
            <img src="{{ asset('images/badges/app-store.svg') }}" alt="{{ __('Download on the App Store') }}" width="144" height="48" class="h-12 w-auto">
        </a>
    </li>
</ul>
