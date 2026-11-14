@props(['status'])

@php
    $styles = [
        'confirmed' => ['Confirmée', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'checked_in' => ['En séjour', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
        'pending' => ['En attente', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        'cancelled' => ['Annulée', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        'completed' => ['Terminée', 'bg-slate-100 text-slate-600 ring-slate-500/20'],
    ];
    [$label, $class] = $styles[$status] ?? [$status, 'bg-slate-100 text-slate-600 ring-slate-500/20'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {$class}"]) }}>{{ $label }}</span>
