@php
    $locale = app()->getLocale();
    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $heading, 'description' => $description, 'inLanguage' => $locale,
            'url' => localized_route('privacy'), 'dateModified' => $updated->toDateString()],
    ];
@endphp
@extends('layouts.site')

@section('content')
    <section class="gutter bg-mist pt-8 pb-14 sm:pt-12 sm:pb-20" aria-labelledby="privacy-title">
        <div class="wrap grid gap-6">
            <h1 id="privacy-title" class="max-w-[17em] text-[clamp(38px,5.2vw,68px)] leading-[1.04]">{{ $heading }}</h1>
            <p class="max-w-[36em] text-[clamp(18px,1.7vw,21px)] text-ink-2">{{ $description }}</p>
            <p class="text-[15px] text-ink-2">{{ __('Updated :date', ['date' => $updated->locale($locale)->isoFormat('LL')]) }}</p>
        </div>
    </section>

    <div class="gutter py-14 sm:py-20">
        <div class="wrap">
            <article class="prose max-w-[41rem]">{!! $html !!}</article>
        </div>
    </div>
@endsection
