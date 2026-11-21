<x-layouts.admin title="Garasi" subtitle="Daftar seluruh garasi dan status hari ini">
    <x-slot:actions>
        <a href="{{ route('admin.garages.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Buat Garasi</a>
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.garages.index') }}" @class(['btn btn-sm', 'btn-dark' => ! $type, 'btn-ghost' => $type])>Semua</a>
        @foreach (\App\Enums\GarageType::cases() as $t)
            <a href="{{ route('admin.garages.index', ['type' => $t->value]) }}" @class(['btn btn-sm', 'btn-dark' => $type === $t, 'btn-ghost' => $type !== $t])>{{ $t->label() }}</a>
        @endforeach
    </div>

    <div class="card table-wrap">
        <table class="table">
            <thead>
                <tr><th>Kode</th><th>Tipe</th><th>Harga harian</th><th>Harga bulanan</th><th>Harga listrik / kWh</th><th>Status hari ini</th></tr>
            </thead>
            <tbody>
                @forelse ($garages as $garage)
                    <tr>
                        <td class="font-display text-lg font-semibold tracking-wider">{{ $garage->code }}</td>
                        <td>{{ $garage->type->label() }}</td>
                        <td>@rupiah($garage->daily_price)</td>
                        <td>@rupiah($garage->monthly_price)</td>
                        <td>{{ $garage->electricity_price_per_kwh ? \App\Support\Format::rupiah($garage->electricity_price_per_kwh) : '—' }}</td>
                        <td>
                            @if ($garage->currentRental)
                                <span class="badge badge-ember">Dipakai</span>
                                <span class="ml-1 text-xs text-charcoal-500">s.d. {{ $garage->currentRental->end_date->translatedFormat('j M') }} · {{ $garage->currentRental->penyewa->name }}</span>
                            @else
                                <span class="badge badge-olive">Kosong</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-charcoal-500">Belum ada garasi. Buat kelompok garasi pertama.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $garages->links() }}</div>
</x-layouts.admin>
