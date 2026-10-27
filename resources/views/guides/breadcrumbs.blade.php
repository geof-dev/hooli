{{-- Where a guide sits: Hooli, the guides, then $trail, the pages below them. --}}
<nav aria-label="{{ __('Breadcrumb') }}">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[15px] text-ink-2">
        <li><a class="hover:text-ink hover:underline" href="{{ localized_route('home') }}">Hooli</a></li>
        <li aria-hidden="true">/</li>
        @if ($trail)
            <li><a class="hover:text-ink hover:underline" href="{{ localized_route('guides') }}">{{ __('Guides') }}</a></li>
            @foreach ($trail as $item)
                <li aria-hidden="true">/</li>
                <li aria-current="page">{{ $item }}</li>
            @endforeach
        @else
            <li aria-current="page">{{ __('Guides') }}</li>
        @endif
    </ol>
</nav>
