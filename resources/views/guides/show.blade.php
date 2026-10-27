@php
    $title = $guide->title.' | Hooli';
    $description = $guide->description;
    $ogType = 'article';
    $locale = app()->getLocale();

    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $guide->title, 'description' => $description, 'inLanguage' => $locale,
            'url' => $guide->url(), 'mainEntityOfPage' => $guide->url(), 'image' => asset($locale === 'en' ? 'images/og.jpg' : "images/og-$locale.jpg"),
            'datePublished' => $guide->published->toDateString(), 'dateModified' => $guide->updated->toDateString(),
            'author' => ['@type' => 'Organization', 'name' => 'Hooli', 'url' => url('/')],
            'publisher' => ['@type' => 'Organization', 'name' => 'Hooli', 'url' => url('/'), 'logo' => ['@type' => 'ImageObject', 'url' => asset('images/icon-512.png')]]],
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Hooli', 'item' => localized_route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('Guides'), 'item' => localized_route('guides')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $guide->short, 'item' => $guide->url()],
        ]],
    ];
@endphp
@extends('layouts.site')

@section('content')
    <section class="gutter bg-mist pt-8 pb-14 sm:pt-12 sm:pb-20" aria-labelledby="guide-title">
        <div class="wrap grid gap-6">
            @include('guides.breadcrumbs', ['trail' => [$guide->short]])
            <h1 id="guide-title" class="max-w-[17em] text-[clamp(38px,5.2vw,68px)] leading-[1.04]">{{ $guide->title }}</h1>
            <p class="max-w-[36em] text-[clamp(18px,1.7vw,21px)] text-ink-2">{{ $guide->description }}</p>
            <p class="flex flex-wrap gap-x-6 gap-y-1 text-[15px] text-ink-2">
                <span>{{ __('Updated :date', ['date' => $guide->updated->locale($locale)->isoFormat('LL')]) }}</span>
                <span>{{ __(':count min read', ['count' => $guide->minutes]) }}</span>
            </p>
        </div>
    </section>

    <div class="gutter py-14 sm:py-20">
        <div class="wrap grid gap-10 lg:grid-cols-[14rem_minmax(0,41rem)] lg:justify-between lg:gap-16">
            {{-- The sections, to jump to: beside the text on wide screens, folded above it on phones. --}}
            <nav class="lg:order-none" aria-labelledby="contents-title">
                <details class="group rounded-[20px] bg-cream shadow-[0_0_0_1px_var(--color-line)] lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 font-semibold [&::-webkit-details-marker]:hidden">
                        {{ __('On this page') }}
                        <svg viewBox="0 0 16 16" class="size-4 text-sage-deep transition group-open:rotate-45" aria-hidden="true"><path d="M8 2v12M2 8h12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </summary>
                    <ol class="grid gap-2 px-5 pb-5 text-[16px]">
                        @foreach ($guide->contents as $id => $heading)
                            <li><a class="text-ink-2 hover:text-ink hover:underline" href="#{{ $id }}">{{ $heading }}</a></li>
                        @endforeach
                    </ol>
                </details>
                <div class="sticky top-8 hidden gap-3 lg:grid">
                    <p id="contents-title" class="font-semibold">{{ __('On this page') }}</p>
                    <ol class="grid gap-2.5 border-l border-line text-[15px] leading-snug">
                        @foreach ($guide->contents as $id => $heading)
                            <li><a class="-ml-px block border-l border-transparent pl-4 text-ink-2 hover:border-sage-deep hover:text-ink" href="#{{ $id }}">{{ $heading }}</a></li>
                        @endforeach
                    </ol>
                </div>
            </nav>

            <article class="grid gap-10">
                {{-- The answer first, for the reader in a hurry. --}}
                <div class="rounded-[24px] bg-cream p-6 shadow-[0_0_0_1px_var(--color-line)] sm:p-8">
                    <p class="font-display text-[26px] leading-tight">{{ __('In short') }}</p>
                    <ul class="mt-4 grid gap-3">
                        @foreach ($guide->points as $point)
                            <li class="flex gap-3"><span class="mt-[.6em] size-2 shrink-0 rounded-full bg-sage"></span>{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="prose">{!! $guide->html !!}</div>

                <p class="rounded-[20px] bg-blush-pale p-5 text-[16px] text-clay">
                    <span class="font-semibold">{{ __('Hooli provides information, not medical advice.') }}</span>
                    {{ __('It does not replace diagnosis or treatment by a health professional. Always talk to your doctor before changing a medication.') }}
                </p>
            </article>
        </div>
    </div>

    <section class="gutter pb-20 sm:pb-28" aria-labelledby="more-title">
        <div class="wrap grid gap-8 lg:grid-cols-[14rem_minmax(0,41rem)] lg:justify-between lg:gap-16">
            <h2 id="more-title" class="text-[clamp(30px,3.4vw,40px)]">{{ __('More guides') }}</h2>
            @include('guides.list', ['guides' => \App\Guide::all($locale)->reject(fn ($other) => $other->key === $guide->key)])
        </div>
    </section>

    @include('partials.waitlist-band')
@endsection
