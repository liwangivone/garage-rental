<x-layouts.admin title="Perpanjang Sewa" subtitle="Garasi yang sedang disewa hari ini">
    <div class="card table-wrap">
        <table class="table">
            <thead>
                <tr><th>Garasi</th><th>Penyewa</th><th>Kendaraan</th><th>Periode</th><th>Total</th><th class="text-right">Aksi</th></tr>
            </thead>
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
                        <td class="text-right"><a href="{{ route('admin.extensions.show', $rental) }}" class="btn btn-dark btn-sm">Perpanjang</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-charcoal-500">Tidak ada garasi yang sedang disewa hari ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rentals->links() }}</div>
</x-layouts.admin>
