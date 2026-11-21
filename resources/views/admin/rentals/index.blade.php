<x-layouts.admin title="Sewa" subtitle="Verifikasi bukti bayar, perpanjang, dan kelola data sewa">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.rentals.index', ['filter' => $key]) }}" @class(['btn btn-sm', 'btn-dark' => $filter === $key, 'btn-ghost' => $filter !== $key])>
                {{ $label }}@if ($key === 'pending' && $pendingCount) <span class="ml-1 rounded-sm bg-ember-500 px-1.5 text-white">{{ $pendingCount }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="card table-wrap">
        <table class="table">
            <thead><tr><th>Garasi</th><th>Penyewa</th><th>Kendaraan</th><th>Periode</th><th>Total</th><th>Pembayaran</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($rentals as $rental)
                    <tr>
                        <td class="font-display text-lg font-semibold tracking-wider">{{ $rental->garage->code }}</td>
                        <td>{{ $rental->penyewa->name }}</td>
                        <td>{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}
                            <span class="block text-xs text-charcoal-500">{{ $rental->vehicle->plate_number ?? $rental->vehicle->type->label() }}</span></td>
                        <td class="whitespace-nowrap">{{ $rental->start_date->translatedFormat('j M Y') }} – {{ $rental->end_date->translatedFormat('j M Y') }}
                            <span class="block text-xs text-charcoal-500">{{ $rental->monthly_count ? $rental->monthly_count.' bulan' : '' }}{{ $rental->monthly_count && $rental->daily_count ? ' + ' : '' }}{{ $rental->daily_count ? $rental->daily_count.' hari' : '' }}</span></td>
                        <td>@rupiah($rental->total_price)</td>
                        <td><x-payment-badge :status="$rental->payment_status" /></td>
                        <td class="text-right"><a href="{{ route('admin.rentals.show', $rental) }}" class="btn btn-dark btn-sm">{{ $rental->payment_status === \App\Enums\PaymentStatus::Pending ? 'Verifikasi' : 'Detail' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-charcoal-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rentals->links() }}</div>
</x-layouts.admin>
