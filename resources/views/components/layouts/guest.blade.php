@props(['title' => 'Masuk', 'width' => 'max-w-sm'])
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>{{ $title }} · Garage Rental</title>
</head>
<body class="flex min-h-screen items-center justify-center bg-charcoal-900 px-4 py-10">
    <div class="w-full {{ $width }}">
        <div class="accent-bar h-2"></div>
        <div class="bg-cream-50 px-8 py-10">
            <p class="font-display text-3xl font-bold uppercase leading-none tracking-wider text-charcoal-900">Garage<span class="text-ember-500">Rental</span></p>
            <p class="mt-1 text-xs uppercase tracking-[0.2em] text-charcoal-500">Sewa garasi kendaraan</p>
            {{ $slot }}
        </div>
    </div>
</body>
</html>
