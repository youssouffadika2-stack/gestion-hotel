<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
    @if ($hotels->count() > 1)
        <button type="button" @click="open = ! open" class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-navy-900 hover:bg-slate-200" aria-haspopup="listbox" :aria-expanded="open">
            <x-ui.icon name="building-2" class="size-4 text-teal-600" />
            <span class="max-w-32 truncate sm:max-w-48">{{ $current?->name }}</span>
            <x-ui.icon name="chevron-down" class="size-4 text-slate-400" />
        </button>

        <ul x-show="open" x-cloak x-transition @click.outside="open = false" role="listbox" class="absolute left-0 mt-2 w-64 rounded-xl bg-white py-1 shadow-lg ring-1 ring-slate-200">
            @foreach ($hotels as $h)
                <li>
                    <button type="button" wire:click="switchTo({{ $h->id }})" role="option" aria-selected="{{ $h->id === $current?->id ? 'true' : 'false' }}"
                            @class(['flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm hover:bg-slate-50', 'font-semibold text-teal-700' => $h->id === $current?->id])>
                        <span class="truncate">{{ $h->name }}<span class="block text-xs font-normal text-slate-500">{{ $h->city }}</span></span>
                    </button>
                </li>
            @endforeach
        </ul>
    @else
        <div class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-navy-900">
            <x-ui.icon name="building-2" class="size-4 text-teal-600" />
            <span class="max-w-32 truncate sm:max-w-48">{{ $current?->name }}</span>
        </div>
    @endif
</div>
