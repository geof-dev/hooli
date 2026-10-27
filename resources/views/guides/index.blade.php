@php
    $title = __('Guides: hormones, endocrine disruptors and medicines | Hooli');
    $description = __('Calm, sourced guides to endocrine disruptors, cosmetic ingredients and the medicines that may affect hormones. In plain words, by the team building Hooli.');

    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => __('Guides'), 'url' => localized_route('guides'), 'inLanguage' => app()->getLocale(), 'description' => $description,
            'hasPart' => $guides->map(fn ($guide) => ['@type' => 'Article', 'headline' => $guide->title, 'url' => $guide->url()])->all()],
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Hooli', 'item' => localized_route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('Guides'), 'item' => localized_route('guides')],
        ]],
    ];
@endphp
@extends('layouts.site')

@section('content')
    <section class="gutter bg-mist pt-8 pb-16 sm:pt-12 sm:pb-20" aria-labelledby="guides-title">
        <div class="wrap grid gap-6">
            @include('guides.breadcrumbs', ['trail' => []])
            <h1 id="guides-title" class="text-[clamp(44px,6vw,76px)] leading-[1.02]">{{ __('Guides') }}</h1>
            <p class="max-w-[34em] text-[clamp(18px,1.7vw,21px)] text-ink-2">{{ __('The questions that need more than a few lines: what endocrine disruptors are, which ingredients to know, which medicines to ask about. Calm, in plain words, with every source listed.') }}</p>
        </div>
    </section>

    <section class="gutter py-16 sm:py-24" aria-label="{{ __('Guides') }}">
        <div class="wrap max-w-[52rem]">
            @include('guides.list')
        </div>
    </section>

    @include('partials.waitlist-band')
@endsection
