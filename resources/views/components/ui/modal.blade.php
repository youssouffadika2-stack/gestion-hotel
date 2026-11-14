@props(['title', 'maxWidth' => 'lg'])

@php
    $model = $attributes->wire('model')->value();
    $widths = ['md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-2xl'];
@endphp

{{-- Fenêtre modale liée à une propriété Livewire booléenne : <x-ui.modal wire:model="showForm" title="…"> --}}
<div x-data="{ open: $wire.entangle('{{ $model }}').live }"
     x-show="open" x-cloak
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
     role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-navy-900/60"></div>

    <div x-show="open" x-transition
         class="relative flex max-h-[92vh] w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h3 class="text-base font-bold text-navy-900">{{ $title }}</h3>
            <button type="button" @click="open = false" class="hp-icon-btn" aria-label="Fermer"><x-ui.icon name="x" /></button>
        </header>
        <div class="overflow-y-auto px-5 py-4">
            {{ $slot }}
        </div>
        @isset($footer)
            <footer class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-3 sm:flex-row sm:justify-end">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
