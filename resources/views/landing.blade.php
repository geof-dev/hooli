@php
    $title = __('Hooli: care for your hormones, one scan at a time');
    $description = __('Hooli is a mobile app that scans everyday products and medicines for what may affect your hormones, and answers your daily questions with calm, evidence-based advice. Join the waitlist.');
    $contact = config('hooli.contact');
    $guides = \App\Guide::all(app()->getLocale())->keyBy('key');

    // [title, text, the guide that goes further]
    $problems = [
        [__('Labels are hard to read'), __('Some everyday products contain ingredients that may disrupt hormones, called endocrine disruptors. They hide behind names few of us recognise.'), $guides['endocrine-disruptors']],
        [__('Some medicines carry hormonal risks'), __('Certain progestins, for example, are linked to meningioma, a usually benign tumour of the membranes around the brain. It’s worth knowing, and worth asking your doctor about.'), $guides['progestins-meningioma']],
        [__('Advice online is loud'), __('If you live with a thyroid or hormonal condition, you’ve probably read more alarm than answers.'), null],
    ];

    // [title, text, a small piece of the app that shows it]
    $steps = [
        [__('Scan'), __('Point your camera at a barcode or an ingredient list: cosmetics, food, cleaning products, medicines.'), null],
        [__('Understand'), __('Every ingredient explained in a sentence. The ones that may affect hormones are pointed out, for your situation.'), ['Propylparaben', __('Worth knowing'), 'know']],
        [__('Swap'), __('When something is worth replacing, Hooli suggests better options sold in your country.'), [__('Fragrance-free lotion'), __('No concern'), 'fine']],
    ];

    $features = [
        ['scan', __('Scan anything'), __('A barcode or a photo of the label, for cosmetics, food and household products. Every ingredient in plain language, endocrine disruptors pointed out.')],
        ['profile', __('A profile that’s yours'), __('Thyroid, meningioma, endometriosis, PCOS, a pregnancy plan, menopause: choose your situation, and scores and advice adapt to you.')],
        ['swap', __('Safer swaps'), __('When a product is worth replacing, see better alternatives you can actually buy where you live.')],
        ['question', __('Everyday questions'), __('Short answers to the questions that come up every day, and one tip each morning. Every answer cites its sources.')],
    ];
    $next = [__('Health journal'), __('Lab results, like TSH and hormones'), __('Reminders'), __('A report for your doctor'), __('An assistant to ask anything'), __('Guided paths'), __('Community')];

    $questions = [
        [
            __('Should I heat food in plastic?'),
            __('Better not. Heat helps chemicals like bisphenols and phthalates move from plastic into food. Warm food in glass or ceramic, and keep plastic containers for cold things.'),
            __('American Academy of Pediatrics, Food Additives and Child Health (2018). EFSA, re-evaluation of bisphenol A (2023).'),
        ],
        [
            __('Is tap water OK for my hormones?'),
            __('In most of Europe, yes. Tap water is tested often, and EU rules now cap bisphenol A and PFAS in it too. On a private well, or with very old pipes, ask your town for its latest water report.'),
            __('EU Drinking Water Directive 2020/2184. WHO, Guidelines for drinking-water quality (2022).'),
        ],
        [
            __('Should I stop wearing sunscreen?'),
            __('No, sun protection matters. If you’d rather avoid filters studied for hormonal effects, like benzophenone-3 or homosalate, choose a mineral sunscreen with zinc oxide or titanium dioxide.'),
            __('SCCS opinions on benzophenone-3 and homosalate (2021). EU Cosmetics Regulation, Annex VI.'),
        ],
    ];

    $faq = [
        ['q' => __('Is Hooli medical advice?'), 'a' => __('No. Hooli gives you information, so you can make everyday choices and bring better questions to your doctor. It doesn’t diagnose anything, and it doesn’t replace diagnosis or treatment by a health professional. Always talk to your doctor before changing a medication.')],
        ['q' => __('What are endocrine disruptors?'), 'a' => __('Substances that can interfere with how hormones work. Some are found in everyday products: certain preservatives and UV filters in cosmetics, bisphenols in some plastics, PFAS in non-stick and waterproof coatings. EU rules ban or limit many of them, and you can lower your exposure further with a few simple habits.'), 'guide' => $guides['endocrine-disruptors']],
        ['q' => __('Is my data private?'), 'a' => __('Yes. Your health profile is used for one thing: adapting Hooli’s advice to you. It is never sold or used for advertising, and you can delete it whenever you like. Hooli follows the GDPR, the European data protection law that gives health data its strongest protection. This website sets no cookies and uses no trackers.')],
        ['q' => __('Where does Hooli’s information come from?'), 'a' => __('From health agencies and scientific assessments, such as those of the European Food Safety Authority, the European Chemicals Agency and the European Medicines Agency. Every answer shows its sources, so you can check them or take them to your doctor.')],
        ['q' => __('Which countries will Hooli be available in?'), 'a' => __('Hooli starts in Europe, in English, with French and Estonian to follow. Safer swaps show products sold in your country. Join the waitlist and we’ll tell you when Hooli opens where you live.')],
        ['q' => __('Is Hooli free?'), 'a' => __('Prices aren’t set yet. Our aim is that scanning products and reading the everyday answers cost nothing. People on the waitlist will hear first.')],
    ];

    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'Organization', '@id' => url('/').'#organization', 'name' => 'Hooli', 'url' => url('/'), 'logo' => asset('images/icon-512.png'), 'email' => $contact],
        ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Hooli', 'url' => url('/'), 'inLanguage' => app()->getLocale(), 'description' => $description, 'publisher' => ['@id' => url('/').'#organization']],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn (array $item) => [
            '@type' => 'Question', 'name' => $item['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
        ], $faq)],
    ];
@endphp
@extends('layouts.site')

@section('content')
    {{-- Hero: the promise, and the thing itself, a label read for you. --}}
    <section class="gutter overflow-hidden bg-mist" aria-labelledby="hero-title">
        <div class="wrap grid items-center gap-12 pt-8 sm:pt-12 lg:grid-cols-[1.08fr_.92fr] lg:gap-10 lg:pt-10 lg:pb-24">
            <div class="grid justify-items-start gap-7">
                <h1 id="hero-title" class="text-[clamp(44px,6.6vw,84px)] leading-[1.02]">{{ __('Care for your hormones, one scan at a time.') }}</h1>
                <p class="max-w-[34em] text-[clamp(18px,1.7vw,21px)] text-ink-2">{{ __('Hooli reads the labels on your cosmetics, food, household products and medicines, and tells you in plain words what matters for your hormones. Calm, evidence-based, and adapted to you.') }}</p>
                {{-- The waitlist, where the promise is: an email is all it takes. The band lower down also asks the situation. --}}
                <div class="grid w-full max-w-[34rem] gap-3">
                    @include('partials.waitlist-form', ['short' => true, 'id' => 'hero'])
                    <p class="text-[15px] text-ink-2">{{ __('A mobile app, in development.') }} {{ __('One email the day it opens.') }}</p>
                </div>
            </div>

            <div class="phone-crop -mb-px flex justify-center lg:justify-end lg:pr-8">
                @include('partials.phone-scan')
            </div>
        </div>
    </section>

    {{-- The problem, in three plain sentences. Not a sequence, so no numbers. --}}
    <section class="gutter py-20 sm:py-28" aria-labelledby="problem-title">
        <div class="wrap grid gap-10 lg:grid-cols-[1fr_1.35fr] lg:gap-20">
            <h2 id="problem-title" class="max-w-[13em] text-[clamp(34px,4.2vw,52px)]">{{ __('The answers exist. They’re just hard to find.') }}</h2>
            <ul class="grid border-t border-line">
                @foreach ($problems as [$heading, $text, $guide])
                    <li class="grid gap-1.5 border-b border-line py-6 sm:grid-cols-[14em_1fr] sm:gap-8">
                        <h3 class="text-[24px] leading-tight">{{ $heading }}</h3>
                        <div class="grid gap-2">
                            <p class="text-ink-2">{{ $text }}</p>
                            @if ($guide)
                                <p class="text-[16px]"><a class="link font-semibold" href="{{ $guide->url() }}">{{ __('Read the guide: :title', ['title' => $guide->short]) }}</a></p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- How it works: a real sequence, so numbered. --}}
    <section class="gutter pb-20 sm:pb-28" aria-labelledby="how-title">
        <div class="wrap">
            <h2 id="how-title" class="text-[clamp(34px,4.2vw,52px)]">{{ __('How Hooli works') }}</h2>
            <ol class="mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
                @foreach ($steps as $i => [$heading, $text, $chip])
                    <li class="relative grid content-start gap-3 md:pt-16">
                        {{-- The thread that joins the steps. --}}
                        @if ($i < 2)
                            <span class="absolute top-[26px] left-16 hidden h-px w-[calc(100%-32px)] bg-line md:block" aria-hidden="true"></span>
                        @endif
                        <span class="font-display text-[56px] leading-none text-sage md:absolute md:top-0 md:left-0" aria-hidden="true">{{ $i + 1 }}</span>
                        <h3 class="text-[28px]">{{ $heading }}</h3>
                        <p class="max-w-[30em] text-ink-2">{{ $text }}</p>
                        @if ($chip)
                            <p class="mt-1 flex w-fit items-center gap-3 rounded-full bg-cream py-1.5 pr-1.5 pl-4 text-[15px] font-semibold shadow-[0_0_0_1px_var(--color-line)]" aria-hidden="true">
                                {{ $chip[0] }} <span class="tag tag-{{ $chip[2] }} text-[13px]">{{ $chip[1] }}</span>
                            </p>
                        @else
                            <p class="mt-1 flex w-fit items-center gap-2.5 rounded-full bg-cream py-1.5 pr-4 pl-1.5 text-[15px] font-semibold shadow-[0_0_0_1px_var(--color-line)]" aria-hidden="true">
                                <span class="grid size-8 place-items-center rounded-full bg-sage-deep text-cream">@include('partials.icon', ['name' => 'scan', 'class' => 'size-[18px]'])</span>
                                {{ __('Barcode or label') }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- The first version. The medication check is what sets Hooli apart, so it gets the room. --}}
    <section class="gutter bg-mist py-20 sm:py-28" aria-labelledby="features-title">
        <div class="wrap">
            <h2 id="features-title" class="max-w-[16em] text-[clamp(34px,4.2vw,52px)]">{{ __('What you can do with Hooli') }}</h2>

            <div class="mt-12 grid grid-cols-[minmax(0,1fr)] items-center gap-10 rounded-[36px] bg-shell p-6 sm:p-10 lg:grid-cols-2 lg:gap-16 lg:p-14">
                <div class="grid content-start gap-4">
                    <span class="grid size-12 place-items-center rounded-2xl bg-blush-pale text-clay">@include('partials.icon', ['name' => 'medication'])</span>
                    <h3 class="text-[clamp(30px,3.2vw,40px)]">{{ __('Check your medication') }}</h3>
                    <p class="max-w-[32em] text-[19px] text-ink-2">{{ __('Scan the box or type the name of a medicine. Hooli tells you about known hormonal risks, in plain words, and suggests the questions to ask your doctor at your next appointment.') }}</p>
                    <p class="max-w-[32em] text-ink-2">{{ __('It never tells you to stop a treatment. That’s a decision for you and your doctor, and Hooli helps you have that conversation.') }}</p>
                </div>
                @include('partials.medication-card')
            </div>

            <ul class="mt-14 grid gap-x-12 gap-y-10 md:grid-cols-2">
                @foreach ($features as [$icon, $heading, $text])
                    <li class="grid grid-cols-[48px_1fr] gap-x-5 gap-y-1.5">
                        <span class="row-span-2 grid size-12 place-items-center rounded-2xl bg-cream text-sage-deep">@include('partials.icon', ['name' => $icon])</span>
                        <h3 class="pt-2 text-[24px] leading-tight">{{ $heading }}</h3>
                        <p class="text-ink-2">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-16 grid gap-4 border-t border-line pt-8 lg:grid-cols-[14em_1fr] lg:gap-8">
                <h3 class="text-[24px]">{{ __('Coming next') }}</h3>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($next as $item)
                        <li class="rounded-full px-4 py-1.5 text-[16px] text-ink-2 shadow-[inset_0_0_0_1.5px_var(--color-line)]">{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Everyday questions, as the app's answer cards: short, calm, sourced. --}}
    <section class="gutter py-20 sm:py-28" aria-labelledby="questions-title">
        <div class="wrap">
            <div class="grid gap-4 lg:grid-cols-[1fr_1.35fr] lg:items-end lg:gap-20">
                <h2 id="questions-title" class="max-w-[12em] text-[clamp(34px,4.2vw,52px)]">{{ __('Clear answers to everyday questions') }}</h2>
                <p class="max-w-[36em] text-[19px] text-ink-2">{{ __('The small questions that come up while you cook, shop or get ready, answered in a few lines. Each answer shows where it comes from.') }}</p>
            </div>

            <ul class="mt-12 grid gap-5 lg:grid-cols-3">
                @foreach ($questions as [$question, $answer, $sources])
                    <li class="flex flex-col gap-4 rounded-[24px] bg-cream p-6 shadow-[0_0_0_1px_var(--color-line)] sm:p-7">
                        <h3 class="text-[26px] leading-tight">{{ $question }}</h3>
                        <p>{{ $answer }}</p>
                        <p class="mt-auto border-t border-line pt-4 text-[14px] leading-snug text-ink-2"><span class="font-semibold">{{ __('Sources') }}:</span> {{ $sources }}</p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-5 flex items-start gap-4 rounded-[24px] bg-mist p-6 sm:items-center sm:p-7">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-cream text-sage-deep">@include('partials.icon', ['name' => 'leaf', 'class' => 'size-6'])</span>
                <p><span class="font-semibold text-sage-deep">{{ __('And a tip each morning.') }}</span> {{ __('Like this one: open your windows for ten minutes a day, even in winter. Fresh air lowers the chemicals that build up indoors from furniture and cleaning products.') }}</p>
            </div>
        </div>
    </section>

    {{-- The guides: for the questions that need more than a few lines. --}}
    <section class="gutter pb-20 sm:pb-28" aria-labelledby="guides-title">
        <div class="wrap grid gap-10 lg:grid-cols-[1fr_1.35fr] lg:gap-20">
            <div class="grid content-start justify-items-start gap-5">
                <h2 id="guides-title" class="max-w-[12em] text-[clamp(34px,4.2vw,52px)]">{{ __('Guides for the bigger questions') }}</h2>
                <p class="max-w-[30em] text-[19px] text-ink-2">{{ __('Longer reads on what we’re asked most, in plain words, with every source listed.') }}</p>
                <a class="link font-semibold" href="{{ localized_route('guides') }}">{{ __('All guides') }}</a>
            </div>
            @include('guides.list', ['guides' => $guides])
        </div>
    </section>

    {{-- Why Hooli exists. --}}
    <section class="gutter bg-blush-pale py-20 sm:py-28" aria-labelledby="story-title">
        <div class="mx-auto grid max-w-[36em] gap-6">
            <h2 id="story-title" class="text-[clamp(34px,4.2vw,52px)]">{{ __('Why I’m building Hooli') }}</h2>
            <p class="font-display text-[clamp(22px,2.4vw,27px)] leading-[1.4]">{{ __('My wife had surgery for a meningioma that had come back: a tumour that can be influenced by hormones.') }}</p>
            <p class="text-[19px] leading-[1.7]">{{ __('Afterwards, we wanted to know what we could change at home: in the bathroom, in the kitchen, in her medicine cabinet. The information was out there, but scattered, technical, and often frightening.') }}</p>
            <p class="text-[19px] leading-[1.7]">{{ __('So I started building the tool we were missing: a calm, simple way to make safer everyday choices, and to walk into the doctor’s office with the right questions. Hooli is for her, and for every woman who wants to take care of her hormones.') }}</p>
            <p class="mt-4 flex items-center gap-4 border-t border-clay/20 pt-6 text-clay">
                @include('partials.icon-tile', ['class' => 'size-9 shrink-0'])
                <span>{{ __('Hooli means “care!” in Estonian, as in hooli endast: take care of yourself.') }}</span>
            </p>
        </div>
    </section>

    @include('partials.waitlist-band')

    {{-- Questions people ask before joining. The same answers feed the FAQ structured data. --}}
    <section id="faq" class="gutter py-20 sm:py-28" aria-labelledby="faq-title">
        <div class="wrap grid gap-10 lg:grid-cols-[1fr_1.35fr] lg:gap-20">
            <h2 id="faq-title" class="text-[clamp(34px,4.2vw,52px)]">{{ __('Questions and answers') }}</h2>
            <div class="border-t border-line">
                @foreach ($faq as $item)
                    <details class="group border-b border-line">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-6 py-5 text-[20px] font-semibold [&::-webkit-details-marker]:hidden">
                            {{ $item['q'] }}
                            <svg viewBox="0 0 16 16" class="mt-1.5 size-4 shrink-0 text-sage-deep transition group-open:rotate-45" aria-hidden="true"><path d="M8 2v12M2 8h12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </summary>
                        <div class="grid max-w-[38em] gap-3 pb-6 text-ink-2">
                            <p>{{ $item['a'] }}</p>
                            @isset($item['guide'])
                                <p class="text-[16px]"><a class="link font-semibold" href="{{ $item['guide']->url() }}">{{ __('Read the guide: :title', ['title' => $item['guide']->short]) }}</a></p>
                            @endisset
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endsection
