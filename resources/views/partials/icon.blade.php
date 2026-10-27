{{-- Line icons for the features, drawn on a 32-unit grid in the text colour. --}}
<svg viewBox="0 0 32 32" class="{{ $class ?? 'size-7' }}" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('scan')
            <path d="M5 11V7.5A2.5 2.5 0 0 1 7.5 5H11M21 5h3.5A2.5 2.5 0 0 1 27 7.5V11M27 21v3.5a2.5 2.5 0 0 1-2.5 2.5H21M11 27H7.5A2.5 2.5 0 0 1 5 24.5V21M10 16h12"/>
            @break
        @case('medication')
            <g transform="rotate(-40 16 16)"><rect x="4" y="11" width="24" height="10" rx="5"/><path d="M16 11v10"/></g>
            @break
        @case('profile')
            <circle cx="16" cy="11" r="5"/><path d="M6 27c1-5.5 5-8.5 10-8.5s9 3 10 8.5"/>
            @break
        @case('swap')
            <path d="M6 11h19l-5-5M26 21H7l5 5"/>
            @break
        @case('question')
            <path d="M8.5 5h15A3.5 3.5 0 0 1 27 8.5v10a3.5 3.5 0 0 1-3.5 3.5H14l-6 5v-5h.5A3.5 3.5 0 0 1 5 18.5v-10A3.5 3.5 0 0 1 8.5 5Z"/><path d="M13.5 11a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.8-.9 1.4v.5"/><path d="M16 18.3v.1"/>
            @break
        @case('leaf')
            <path d="M7 25C7 14 14 7 26 6c0 12-7 19-19 19Z"/><path d="M7 25 18 14"/>
            @break
    @endswitch
</svg>
