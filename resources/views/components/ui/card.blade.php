@props(['title' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-xl bg-white shadow-sm ring-1 ring-slate-200/70']) }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-3 px-5 pt-5">
            @if ($title)
                <h2 class="text-sm font-semibold text-navy-900">{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
