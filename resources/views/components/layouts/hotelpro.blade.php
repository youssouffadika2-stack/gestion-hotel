@php
    $user = auth()->user();
    $hotel = app(\App\Services\CurrentHotel::class)->get();
    $role = $hotel ? $user->roleIn($hotel) : null;
    // Nouvelles demandes de réservation faites par les clients depuis la page publique
    $onlineUnread = ($hotel && \Illuminate\Support\Facades\Gate::allows('viewReservations', $hotel))
        ? \App\Models\Reservation::where('hotel_id', $hotel->id)->where('source', 'online')->whereNull('read_at')->count() : 0;
    $roleLabels = ['owner' => 'Propriétaire', 'manager' => 'Gérant', 'accountant' => 'Comptable', 'receptionist' => 'Réceptionniste', 'staff' => 'Employé de service'];

    // [libellé, icône, route, capacité requise (null = tous les rôles)]
    $menu = [
        ['Tableau de bord', 'layout-dashboard', 'dashboard', null],
        ['Point du jour', 'clock', 'closing.index', 'viewSales'],
        ['Ventes des services', 'concierge-bell', 'sales.index', 'viewSales'],
        ['Réservations', 'calendar-check', 'reservations.index', 'viewReservations'],
        ['Demandes en ligne', 'mail', 'online.index', 'viewReservations'],
        ['Chambres', 'bed-double', 'rooms.index', 'viewReservations'],
        ['Clients', 'users', 'guests.index', 'viewReservations'],
        ['Arrivées et départs', 'arrow-left-right', 'arrivals.index', 'viewReservations'],
        ['Facturation', 'receipt', 'invoices.index', 'viewFinance'],
        ['Paiements', 'wallet', 'payments.index', 'recordPayment'],
        ['Services et catalogue', 'building-2', 'services.index', 'manageCatalog'],
        ['Personnel et accès', 'user-cog', 'staff.index', 'manageHotel'],
        ['Gestion des stocks', 'package', 'stock.index', 'manageOperations'],
        ['Dépenses', 'trending-down', 'expenses.index', 'viewFinance'],
        ['Rapports et statistiques', 'bar-chart-3', 'reports.index', 'viewFinance'],
        ["Paramètres de l'hôtel", 'settings', 'settings.index', 'manageHotel'],
    ];
    $menu = array_values(array_filter($menu, fn ($m) => $m[3] === null || ($hotel && \Illuminate\Support\Facades\Gate::allows($m[3], $hotel))));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Tableau de bord' }} · {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-100 font-sans text-slate-700 antialiased">
<div
    x-data="{
        drawer: false,
        collapsed: false,
        init() { try { this.collapsed = localStorage.getItem('hp-collapsed') === '1' } catch (e) {} },
        toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('hp-collapsed', this.collapsed ? '1' : '0') } catch (e) {} },
    }"
    class="flex min-h-full"
>
    {{-- Fond sombre du tiroir mobile --}}
    <div x-show="drawer" x-transition.opacity x-cloak @click="drawer = false" class="fixed inset-0 z-30 bg-navy-900/60 lg:hidden"></div>

    {{-- ============ SIDEBAR ============ --}}
    <aside
        :class="[drawer ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-20' : 'lg:w-64']"
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-navy-900 text-slate-300 transition-all duration-200 lg:sticky lg:top-0 lg:h-screen lg:shrink-0"
        aria-label="Navigation principale"
    >
        <div class="flex h-16 shrink-0 items-center gap-3 px-5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-500 text-white">
                <x-ui.icon name="building-2" class="size-5" />
            </span>
            <span x-show="! collapsed || drawer" class="text-lg font-bold tracking-wide text-white">HOTEL<span class="text-teal-400">PRO</span></span>
            <button type="button" @click="drawer = false" class="ml-auto rounded p-1 text-slate-400 hover:text-white lg:hidden" aria-label="Fermer le menu">
                <x-ui.icon name="x" />
            </button>
        </div>

        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-2">
            @foreach ($menu as [$label, $icon, $route])
                @php $active = request()->routeIs($route) || request()->routeIs(\Illuminate\Support\Str::before($route, '.').'.*'); @endphp
                <a href="{{ route($route) }}" @class([
                    'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                    'bg-teal-500/15 text-white ring-1 ring-inset ring-teal-400/30' => $active,
                    'hover:bg-white/5 hover:text-white' => ! $active,
                ]) @if($active) aria-current="page" @endif title="{{ $label }}">
                    <x-ui.icon :name="$icon" :class="$active ? 'shrink-0 text-teal-300' : 'shrink-0'" />
                    <span x-show="! collapsed || drawer" class="truncate">{{ $label }}</span>
                    @if ($route === 'online.index' && $onlineUnread > 0)
                        <span class="ml-auto rounded-full bg-rose-500 px-1.5 text-[11px] font-bold leading-5 text-white" aria-label="{{ $onlineUnread }} nouvelle(s) demande(s)">{{ $onlineUnread > 99 ? '99+' : $onlineUnread }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-white/10 p-3">
            <button type="button" @click="toggle()" class="mb-2 hidden w-full items-center gap-3 rounded-lg px-3 py-2 text-xs text-slate-400 hover:bg-white/5 hover:text-white lg:flex">
                <x-ui.icon name="chevrons-left" class="size-4 shrink-0 transition" ::class="collapsed && 'rotate-180'" />
                <span x-show="! collapsed">Réduire le menu</span>
            </button>

            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-teal-500 text-sm font-semibold text-white">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <div x-show="! collapsed || drawer" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ $roleLabels[$role] ?? 'Utilisateur' }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" x-show="! collapsed || drawer">
                    @csrf
                    <button type="submit" class="rounded p-1.5 text-slate-400 hover:bg-white/10 hover:text-white" title="Déconnexion" aria-label="Déconnexion">
                        <x-ui.icon name="log-out" class="size-4" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ============ CONTENU ============ --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                <button type="button" @click="drawer = true" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Ouvrir le menu">
                    <x-ui.icon name="menu" />
                </button>

                <div class="hidden min-w-0 md:block">
                    <p class="truncate text-sm font-semibold text-navy-900">Bonjour, {{ \Illuminate\Support\Str::of($user->name)->explode(' ')->first() }} 👋</p>
                    <p class="truncate text-xs text-slate-500">{{ ucfirst(now()->translatedFormat('l j F Y')) }}</p>
                </div>

                <livewire:dashboard.hotel-switcher />

                <livewire:topbar.search />

                <div class="ml-auto flex items-center gap-1 sm:ml-0">
                    <livewire:topbar.notifications />

                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                        <button type="button" @click="open = ! open" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100" aria-haspopup="true" :aria-expanded="open">
                            <span class="flex size-8 items-center justify-center rounded-full bg-navy-700 text-sm font-semibold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <x-ui.icon name="chevron-down" class="hidden size-4 text-slate-400 sm:block" />
                        </button>
                        <div x-show="open" x-cloak x-transition @click.outside="open = false" class="absolute right-0 mt-2 w-56 rounded-xl bg-white py-1 shadow-lg ring-1 ring-slate-200">
                            <div class="border-b border-slate-100 px-4 py-2">
                                <p class="truncate text-sm font-semibold text-navy-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                            </div>
                            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">Mon profil</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-slate-50">Déconnexion</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Notifications éphémères : $this->dispatch('toast', message: '…', type: 'success|error') --}}
<div x-data="{ toasts: [], add(d) { const t = { id: Date.now() + Math.random(), message: d.message, type: d.type || 'success' }; this.toasts.push(t); setTimeout(() => this.toasts = this.toasts.filter(x => x.id !== t.id), 4500) } }"
     @toast.window="add($event.detail)"
     @if (session('toast')) x-init="add({ message: @js(session('toast')) })" @endif
     class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6" aria-live="polite">
    <template x-for="t in toasts" :key="t.id">
        <div x-transition class="pointer-events-auto max-w-sm rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
             :class="t.type === 'error' ? 'bg-rose-600' : 'bg-emerald-600'" x-text="t.message"></div>
    </template>
</div>

<x-livewire-scripts />

<script>
// Suspension en direct : si l'administration suspend l'hôtel, un message s'affiche sans attendre.
(function () {
    let shown = false;
    async function check() {
        if (shown) return;
        try {
            const r = await fetch('{{ route('hotel.status') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (! r.ok) return;
            const d = await r.json();
            if (! d.blocked) return;
            shown = true;
            const s = d.support || {};
            const why = d.reason === 'expired' ? "L'abonnement de votre hôtel est arrivé à échéance." : "L'accès de votre hôtel a été suspendu.";
            const box = document.createElement('div');
            box.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.85);display:flex;align-items:center;justify-content:center;padding:16px;font-family:sans-serif';
            box.innerHTML = '<div style="background:#fff;border-radius:16px;max-width:420px;width:100%;padding:28px;text-align:center">'
                + '<div style="font-size:42px">⛔</div><h2 style="margin:8px 0;font-size:20px;color:#0f172a">Accès suspendu</h2>'
                + '<p style="color:#475569;font-size:14px">' + why + ' Veuillez appeler notre entreprise de gestion de l\'application pour le rétablir.</p>'
                + (s.phone ? '<p style="margin:14px 0;font-size:22px;font-weight:700"><a href="tel:' + s.phone.replace(/\s+/g, '') + '" style="color:#0d9488;text-decoration:none">📞 ' + s.phone + '</a></p>' : '')
                + (s.email ? '<p style="color:#475569;font-size:14px">' + s.email + '</p>' : '')
                + '<button onclick="location.reload()" style="margin-top:14px;background:#0f172a;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-weight:600;cursor:pointer">J\'ai compris</button></div>';
            document.body.appendChild(box);
        } catch (e) {}
    }
    setInterval(check, 15000);
    document.addEventListener('visibilitychange', () => { if (! document.hidden) check(); });
})();
</script>
</body>
</html>
