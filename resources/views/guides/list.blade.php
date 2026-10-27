{{-- The guides as a list of rows, each one a link: on the home page, the guides page and under each guide. --}}
<ul class="border-t border-line">
    @foreach ($guides as $guide)
        <li class="border-b border-line">
            <a href="{{ $guide->url() }}" class="group grid gap-2 py-6">
                <span class="font-display text-[clamp(24px,2.4vw,30px)] leading-tight text-ink decoration-1 underline-offset-[.18em] group-hover:text-sage-deep group-hover:underline">{{ $guide->title }}</span>
                <span class="max-w-[38em] text-ink-2">{{ $guide->description }}</span>
                <span class="text-[15px] font-semibold text-sage-deep">{{ __(':count min read', ['count' => $guide->minutes]) }}</span>
            </a>
        </li>
    @endforeach
</ul>
