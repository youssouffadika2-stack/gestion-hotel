@php
    $icons = [
        'reservation_created' => ['calendar-check', 'bg-amber-50 text-amber-600'],
        'reservation_updated' => ['calendar', 'bg-slate-100 text-slate-600'],
        'payment_recorded' => ['wallet', 'bg-teal-50 text-teal-600'],
        'guest_arrived' => ['user-check', 'bg-emerald-50 text-emerald-600'],
        'guest_departed' => ['log-out', 'bg-navy-50 text-navy-700'],
        'room_added' => ['bed-double', 'bg-sky-50 text-sky-600'],
    ];
@endphp

<div wire:poll.60s>
    <x-ui.card title="Activités récentes">
        @if ($activities->isEmpty())
            <x-ui.empty-state icon="clock" message="Aucune activité récente." />
        @else
            <ul class="space-y-4">
                @foreach ($activities as $a)
                    @php [$icon, $tone] = $icons[$a->type] ?? ['circle-dot', 'bg-slate-100 text-slate-600']; @endphp
                    <li wire:key="act-{{ $a->id }}" class="flex gap-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $tone }}"><x-ui.icon :name="$icon" class="size-4" /></span>
                        <div class="min-w-0">
                            <p class="text-sm text-slate-700">{{ $a->description }}</p>
                            <p class="text-xs text-slate-400">
                                @if ($a->user) {{ $a->user->name }} · @endif
                                <time datetime="{{ $a->created_at->toIso8601String() }}">{{ $a->created_at->diffForHumans() }}</time>
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
