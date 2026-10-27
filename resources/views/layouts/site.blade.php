{{--
    Every page's frame: what search engines and link previews read, the header and the footer. A page sets $title,
    $description and $jsonLd, then fills the content section. $alternates are the page's addresses in each language;
    a page whose route takes no parameters (home, guides) gets them from its route name.
--}}
@php
    $locale = app()->getLocale();
    $alternates ??= collect(config('hooli.locales'))
        ->map(fn (array $language, string $code) => localized_route(\Illuminate\Support\Str::after(request()->route()->getName(), '.'), locale: $code))
        ->all();
    $ogImage = asset($locale === 'en' ? 'images/og.jpg' : "images/og-$locale.jpg");
    $contact = config('hooli.contact');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#E3EADD">
    {{-- Lets the hero's phone wait, as a camera, until it's in view (app.css, .phone). --}}
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $alternates[$locale] }}">
    @foreach ($alternates as $code => $url)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternates['en'] }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:site_name" content="Hooli">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $alternates[$locale] }}">
    <meta property="og:locale" content="{{ config("hooli.locales.$locale.og") }}">
    @foreach (config('hooli.locales') as $code => $language)
        @continue($code === $locale)
        <meta property="og:locale:alternate" content="{{ $language['og'] }}">
    @endforeach
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ __('Hooli. Care for your hormones, one scan at a time.') }}">
    <meta name="twitter:card" content="summary_large_image">
    @foreach ($jsonLd ?? [] as $data)
        <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endforeach

    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-sage-deep focus:px-4 focus:py-2 focus:font-semibold focus:text-cream">{{ __('Skip to content') }}</a>

{{-- For a visitor whose browser prefers another of the site's languages: app.js shows the one that matches. --}}
@foreach ($alternates as $code => $url)
    @continue($code === $locale)
    <div class="gutter bg-pine text-on-pine on-pine" data-lang-suggest="{{ $code }}" lang="{{ $code }}" hidden>
        <p class="wrap flex items-center justify-between gap-4 py-2.5 text-[15px]">
            <span>{{ __('This page is in English too.', locale: $code) }} <a class="link font-semibold" href="{{ $url }}" hreflang="{{ $code }}">{{ __('Read it in English', locale: $code) }}</a></span>
            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full hover:bg-white/10" data-lang-suggest-close aria-label="{{ __('Close', locale: $code) }}">
                <svg viewBox="0 0 16 16" class="size-3.5" aria-hidden="true"><path d="M3 3l10 10M13 3 3 13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </p>
    </div>
@endforeach

<header class="gutter bg-mist">
    <div class="wrap flex items-center justify-between gap-3 py-4 sm:py-5">
        <a href="{{ localized_route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ __('Hooli, home') }}">
            @include('partials.icon-tile', ['class' => 'size-7 sm:size-8'])
            <span class="font-display text-[28px] leading-none text-ink sm:text-[32px]">Hooli</span>
        </a>
        <nav class="flex items-center gap-1 sm:gap-3" aria-label="{{ __('Main') }}">
            <a href="{{ localized_route('guides') }}" class="hidden rounded-full px-3 py-2 font-semibold text-ink-2 hover:text-ink sm:block" @if (request()->routeIs('*.guide*')) aria-current="page" @endif>{{ __('Guides') }}</a>
            @include('partials.language-menu')
            <a href="#waitlist" class="btn ml-1 px-4 py-2.5 text-[15px]">
                <span class="sm:hidden">{{ __('Join') }}</span><span class="hidden sm:inline">{{ __('Join the waitlist') }}</span>
            </a>
        </nav>
    </div>
</header>

<main id="main">
    @yield('content')
</main>

<footer class="on-pine gutter bg-pine pt-16 pb-10 text-on-pine">
    <div class="wrap grid gap-10 md:grid-cols-[1fr_1fr_1.4fr] md:gap-12">
        <div class="grid content-start gap-4">
            <a href="{{ localized_route('home') }}" class="flex w-fit items-center gap-2.5 text-on-pine" aria-label="{{ __('Hooli, home') }}">
                @include('partials.icon-tile', ['class' => 'size-7'])
                <span class="font-display text-[28px] leading-none">Hooli</span>
            </a>
            <p class="max-w-[24em] text-on-pine-2">{{ __('Care for your hormones, one scan at a time. A mobile app, in development.') }}</p>
            <p><a class="link" href="mailto:{{ $contact }}">{{ $contact }}</a></p>
        </div>
        <nav class="grid content-start gap-6 sm:grid-cols-2 md:grid-cols-1" aria-label="{{ __('Footer') }}">
            <div class="grid content-start gap-2">
                <p class="font-semibold"><a class="hover:underline" href="{{ localized_route('guides') }}">{{ __('Guides') }}</a></p>
                <ul class="grid gap-1.5 text-[16px] text-on-pine-2">
                    @foreach (\App\Guide::all($locale) as $guide)
                        <li><a class="hover:text-on-pine hover:underline" href="{{ $guide->url() }}">{{ $guide->short }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="grid content-start gap-2">
                <p class="font-semibold">{{ __('Language') }}</p>
                <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-[16px] text-on-pine-2">
                    @foreach ($alternates as $code => $url)
                        <li><a class="hover:text-on-pine hover:underline" href="{{ $url }}" hreflang="{{ $code }}" lang="{{ $code }}" @if ($code === $locale) aria-current="page" @endif>{{ config("hooli.locales.$code.name") }}</a></li>
                    @endforeach
                </ul>
            </div>
        </nav>
        <p class="h-fit max-w-[38em] rounded-[20px] p-5 text-[16px] text-on-pine-2 shadow-[inset_0_0_0_1.5px_var(--color-line-pine)]">
            <span class="font-semibold text-on-pine">{{ __('Hooli provides information, not medical advice.') }}</span>
            {{ __('It does not replace diagnosis or treatment by a health professional. Always talk to your doctor before changing a medication.') }}
        </p>
    </div>
    <p class="wrap mt-12 border-t border-line-pine pt-6 text-[15px] text-on-pine-2">© {{ date('Y') }} Hooli. {{ __('No cookies, no trackers on this site.') }}</p>
</footer>
</body>
</html>
