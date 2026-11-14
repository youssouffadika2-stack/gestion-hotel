@props([
    'label',
    'value',
    'icon' => 'circle-dot',
    'hint' => null,
    'trend' => null,      // nombre : >0 hausse, <0 baisse, null = rien à afficher
    'trendSuffix' => '%',
    'tone' => 'navy',     // navy | teal | amber | rose | emerald
    'invertTrend' => false, // true : une hausse est une mauvaise chose
])

@php
    $tones = [
        'navy' => 'bg-navy-50 text-navy-700',
        'teal' => 'bg-teal-50 text-teal-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
    ];
    $good = $trend !== null && ($invertTrend ? $trend < 0 : $trend > 0);
    $bad = $trend !== null && ($invertTrend ? $trend > 0 : $trend < 0);
@endphp

<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 transition hover:shadow-md">
    <div class="flex items-start justify-between gap-2">
        <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['navy'] }}">
            <x-ui.icon :name="$icon" class="size-4" />
        </span>
    </div>

    <p class="mt-3 text-xl font-bold tracking-tight text-navy-900 sm:text-2xl">{{ $value }}</p>

    <div class="mt-1 flex items-center gap-1.5 text-xs">
        @if ($trend !== null)
            <span @class([
                'inline-flex items-center gap-0.5 font-semibold',
                'text-emerald-600' => $good,
                'text-rose-600' => $bad,
                'text-slate-500' => ! $good && ! $bad,
            ])>
                <x-ui.icon :name="$trend < 0 ? 'trending-down' : 'trending-up'" class="size-3.5" />
                {{ $trend > 0 ? '+' : '' }}{{ $trend }}{{ $trendSuffix }}
            </span>
        @endif
        @if ($hint)
            <span class="text-slate-500">{{ $hint }}</span>
        @endif
    </div>
</div>
