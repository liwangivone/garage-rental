<x-layouts.customer title="Sewa Saya" subtitle="Riwayat seluruh sewa garasi Anda">
    <x-slot:actions>
        @if (auth()->user()->isApproved())
            <a href="{{ route('penyewa.rentals.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Sewa Baru</a>
        @endif
    </x-slot:actions>

    <div class="card table-wrap">
        <table class="table">
            <thead><tr><th>Garasi</th><th>Kendaraan</th><th>Periode</th><th>Tipe</th><th>Total</th><th>Status</th><th>Pembayaran</th><th></th></tr></thead>
            <tbody>
                @forelse ($rentals as $rental)
                    <tr>
                        <td class="font-display text-lg font-semibold tracking-wider">{{ $rental->garage->code }}</td>
                        <td>{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}
                            <span class="block text-xs text-charcoal-500">{{ $rental->vehicle->plate_number ?? $rental->vehicle->type->label() }}</span></td>
                        <td class="whitespace-nowrap">{{ $rental->start_date->translatedFormat('j M Y') }} – {{ $rental->end_date->translatedFormat('j M Y') }}</td>
                        <td>{{ $rental->rental_type === \App\Enums\RentalType::Monthly ? 'Bulanan' : 'Harian' }}</td>
                        <td>@rupiah($rental->total_price)</td>
                        <td>@include('penyewa.rentals._phase', ['rental' => $rental])</td>
                        <td><x-payment-badge :status="$rental->payment_status" /></td>
                        <td class="text-right"><a href="{{ route('penyewa.rentals.show', $rental) }}" class="text-xs font-semibold uppercase tracking-wider text-ember-600 hover:underline">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-charcoal-500">Belum ada sewa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rentals->links() }}</div>
</x-layouts.customer>
