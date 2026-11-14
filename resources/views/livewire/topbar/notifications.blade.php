<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" wire:poll.60s>
    <button type="button" @click="open = ! open" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Notifications ({{ $count }})" aria-haspopup="true">
        <x-ui.icon name="bell" />
        @if ($count > 0)
            <span class="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $count > 99 ? '99+' : $count }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl bg-white py-2 shadow-lg ring-1 ring-slate-200">
        <p class="px-4 pb-2 text-sm font-semibold text-navy-900">Notifications</p>
        @forelse ($items as $item)
            <a href="{{ $item['url'] }}" class="flex items-start gap-3 px-4 py-2.5 text-sm hover:bg-slate-50">
                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600"><x-ui.icon :name="$item['icon']" class="size-4" /></span>
                <span>{{ $item['text'] }}</span>
            </a>
        @empty
            <x-ui.empty-state icon="bell" message="Rien à signaler pour le moment." />
        @endforelse
    </div>
</div>
