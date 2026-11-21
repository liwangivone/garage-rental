<a href="{{ route('penyewa.rentals.show', $rental) }}" class="card block p-5 transition-colors hover:border-ember-500">
    <div class="flex items-start justify-between">
        <p class="font-display text-3xl font-semibold tracking-wider text-charcoal-900">{{ $rental->garage->code }}</p>
        <div class="flex flex-col items-end gap-1">
            @include('penyewa.rentals._phase', ['rental' => $rental])
            <x-payment-badge :status="$rental->payment_status" />
        </div>
    </div>
    <p class="mt-2 text-sm font-medium">{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}
        <span class="text-charcoal-500">{{ $rental->vehicle->plate_number }}</span></p>
    <p class="mt-1 text-sm text-charcoal-600">{{ $rental->start_date->translatedFormat('j M Y') }} – {{ $rental->end_date->translatedFormat('j M Y') }}</p>
    <p class="mt-3 text-sm font-semibold">@rupiah($rental->total_price)</p>
</a>
