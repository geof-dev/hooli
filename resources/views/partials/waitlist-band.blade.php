{{-- The one thing we ask, on every page: the header's button leads here. --}}
<section id="waitlist" class="on-pine gutter bg-pine py-20 text-on-pine sm:py-28" aria-labelledby="waitlist-title">
    <div class="wrap grid gap-12 lg:grid-cols-[1fr_1fr] lg:gap-20">
        <div class="grid content-start gap-5">
            <h2 id="waitlist-title" class="max-w-[12em] text-[clamp(34px,4.4vw,56px)]">{{ __('Be among the first to use Hooli') }}</h2>
            <p class="max-w-[30em] text-[19px] text-on-pine-2">{{ __('Leave your email and we’ll write once, the day Hooli opens. Tell us your situation too, if you like: it helps us decide what to build first.') }}</p>
            <p class="max-w-[30em] text-[16px] text-on-pine-2">{{ __('No spam. To be removed, write to') }} <a class="link" href="mailto:{{ config('hooli.contact') }}">{{ config('hooli.contact') }}</a>.</p>
        </div>
        @include('partials.waitlist-form')
    </div>
</section>
