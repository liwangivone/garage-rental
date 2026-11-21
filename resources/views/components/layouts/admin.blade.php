<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'Admin' }} · Garage Rental</title>
</head>
<body class="min-h-screen">
@php
    $nav = [
        ['admin.dashboard', 'Dashboard', 'dashboard', 'admin.dashboard'],
        ['admin.garages.index', 'Garasi', 'garage', 'admin.garages.*'],
        ['admin.registrations.index', 'Verifikasi Pendaftar', 'users', 'admin.registrations.*'],
        ['admin.rentals.index', 'Sewa', 'clock', 'admin.rentals.*'],
        ['admin.reports.index', 'Laporan', 'chart', 'admin.reports.*'],
    ];
    $pendingCount = \App\Models\User::pendingVerification()->count();
    $pendingRentals = \App\Models\Rental::where('payment_status', 'pending')->count();
@endphp

<div id="sidebar-backdrop" data-sidebar-toggle class="fixed inset-0 z-30 hidden bg-charcoal-950/60 lg:hidden"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-charcoal-900 text-cream-100 transition-transform lg:translate-x-0">
    <div class="hazard h-1.5"></div>
    <div class="px-6 py-6">
        <p class="font-display text-2xl font-bold uppercase leading-none tracking-wider text-cream-50">Garage<span class="text-ember-500">Rental</span></p>
        <p class="mt-1 text-xs uppercase tracking-[0.2em] text-charcoal-400">Panel Admin</p>
    </div>
    <nav class="flex-1 space-y-0.5 px-3">
        @foreach ($nav as [$route, $label, $icon, $pattern])
            @php $active = request()->routeIs($pattern); @endphp
            <a href="{{ route($route) }}" @class([
                'flex items-center gap-3 border-l-2 px-3 py-2.5 text-sm font-medium uppercase tracking-wider transition-colors',
                'border-ember-500 bg-charcoal-800 text-cream-50' => $active,
                'border-transparent text-charcoal-400 hover:bg-charcoal-800 hover:text-cream-50' => ! $active,
            ])>
                <x-icon :name="$icon" />
                <span class="flex-1">{{ $label }}</span>
                @if ($route === 'admin.registrations.index' && $pendingCount > 0)
                    <span class="rounded-sm bg-ember-500 px-1.5 text-xs font-semibold text-white">{{ $pendingCount }}</span>
                @endif
                @if ($route === 'admin.rentals.index' && $pendingRentals > 0)
                    <span class="rounded-sm bg-ember-500 px-1.5 text-xs font-semibold text-white">{{ $pendingRentals }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <div class="border-t border-charcoal-700 px-6 py-4">
        <p class="truncate text-sm font-medium text-cream-50">{{ auth()->user()->name }}</p>
        <p class="truncate text-xs text-charcoal-400">{{ auth()->user()->email }}</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button class="flex cursor-pointer items-center gap-2 text-xs font-semibold uppercase tracking-wider text-charcoal-400 hover:text-ember-500">
                <x-icon name="logout" class="size-4" /> Keluar
            </button>
        </form>
    </div>
</aside>

<div class="lg:pl-64">
    <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-cream-200 bg-cream-100/90 px-4 py-3 backdrop-blur lg:hidden">
        <button data-sidebar-toggle class="cursor-pointer text-charcoal-800" aria-label="Menu"><x-icon name="menu" class="size-6" /></button>
        <p class="font-display text-lg font-bold uppercase tracking-wider">Garage<span class="text-ember-500">Rental</span></p>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-semibold">{{ $title ?? '' }}</h1>
                @isset($subtitle)<p class="mt-1 text-sm text-charcoal-500">{{ $subtitle }}</p>@endisset
            </div>
            @isset($actions){{ $actions }}@endisset
        </div>
        <x-flash />
        {{ $slot }}
    </main>
</div>
</body>
</html>
