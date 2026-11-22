<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'Beranda' }} · Garage Rental</title>
</head>
<body class="min-h-screen">
@php
    $nav = [
        ['penyewa.dashboard', 'Beranda', 'penyewa.dashboard'],
        ['penyewa.rentals.index', 'Sewa Saya', 'penyewa.rentals.index'],
    ];
@endphp
<header class="bg-charcoal-900 text-cream-100">
    <div class="accent-bar h-1.5"></div>
    <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 sm:px-8">
        <a href="{{ route('penyewa.dashboard') }}" class="font-display text-xl font-bold uppercase tracking-wider text-cream-50">Garage<span class="text-ember-500">Rental</span></a>
        <nav class="flex flex-1 items-center gap-1 text-sm font-medium uppercase tracking-wider">
            @foreach ($nav as [$route, $label, $pattern])
                <a href="{{ route($route) }}" @class([
                    'border-b-2 px-3 py-2 transition-colors',
                    'border-ember-500 text-cream-50' => request()->routeIs($pattern),
                    'border-transparent text-charcoal-400 hover:text-cream-50' => ! request()->routeIs($pattern),
                ])>{{ $label }}</a>
            @endforeach
            @if (auth()->user()->isApproved())
                <a href="{{ route('penyewa.rentals.create') }}" class="btn btn-primary btn-sm ml-2"><x-icon name="plus" class="size-4" /> Sewa Baru</a>
            @endif
        </nav>
        <div class="flex items-center gap-4">
            <span class="hidden text-sm text-charcoal-400 sm:block">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="flex cursor-pointer items-center gap-2 text-xs font-semibold uppercase tracking-wider text-charcoal-400 hover:text-ember-500">
                    <x-icon name="logout" class="size-4" /> Keluar
                </button>
            </form>
        </div>
    </div>
</header>

<main class="mx-auto max-w-5xl px-4 py-8 sm:px-8">
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
</body>
</html>
