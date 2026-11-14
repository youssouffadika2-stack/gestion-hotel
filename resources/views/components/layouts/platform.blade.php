<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Administration plateforme' }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-100 font-sans text-slate-700 antialiased">
<header class="border-b border-slate-200 bg-navy-900 text-white">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
        <span class="flex size-9 items-center justify-center rounded-lg bg-teal-500"><x-ui.icon name="building-2" class="size-5" /></span>
        <div>
            <p class="text-lg font-bold leading-tight tracking-wide">HOTEL<span class="text-teal-400">PRO</span></p>
            <p class="text-[11px] uppercase tracking-wider text-slate-300">Administration de la plateforme</p>
        </div>
        <div class="ml-auto flex items-center gap-3">
            <span class="hidden text-sm text-slate-300 sm:block">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-200 hover:bg-white/10"><x-ui.icon name="log-out" class="size-4" />Déconnexion</button>
            </form>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
    {{ $slot }}
</main>

<div x-data="{ toasts: [], add(d) { const t = { id: Date.now() + Math.random(), message: d.message, type: d.type || 'success' }; this.toasts.push(t); setTimeout(() => this.toasts = this.toasts.filter(x => x.id !== t.id), 4500) } }"
     @toast.window="add($event.detail)"
     class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6" aria-live="polite">
    <template x-for="t in toasts" :key="t.id">
        <div x-transition class="pointer-events-auto max-w-sm rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
             :class="t.type === 'error' ? 'bg-rose-600' : 'bg-emerald-600'" x-text="t.message"></div>
    </template>
</div>

<x-livewire-scripts />
</body>
</html>
