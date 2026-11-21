<x-layouts.admin title="Garasi" subtitle="Daftar seluruh garasi dan status hari ini">
    <x-slot:actions>
        <a href="{{ route('admin.garages.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Buat Garasi</a>
    </x-slot:actions>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        @foreach (\App\Enums\GarageType::cases() as $t)
            @php $pr = $prices[$t->value] ?? null; @endphp
            <div class="card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-charcoal-500">{{ $t->label() }}</p>
                @if ($pr)
                    <p class="mt-1 font-display text-2xl font-semibold">@rupiah($pr->daily_price)<span class="text-sm text-charcoal-500"> /hari</span></p>
                    <p class="text-xs text-charcoal-500">Bulanan @rupiah($pr->monthly_price)@if ($pr->electricity_price_per_kwh) · listrik @rupiah($pr->electricity_price_per_kwh)/kWh @endif</p>
                @else
                    <p class="mt-1 text-sm text-charcoal-500">Belum ada garasi / harga.</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.garages.index') }}" @class(['btn btn-sm', 'btn-dark' => ! $type, 'btn-ghost' => $type])>Semua</a>
        @foreach (\App\Enums\GarageType::cases() as $t)
            <a href="{{ route('admin.garages.index', ['type' => $t->value]) }}" @class(['btn btn-sm', 'btn-dark' => $type === $t, 'btn-ghost' => $type !== $t])>{{ $t->label() }}</a>
        @endforeach
    </div>

    <div class="card table-wrap">
        <table class="table">
            <thead>
                <tr><th>Kode</th><th>Tipe</th><th>Kelompok</th><th>Status hari ini</th></tr>
            </thead>
            <tbody>
                @forelse ($garages as $garage)
                    <tr>
                        <td class="font-display text-lg font-semibold tracking-wider">{{ $garage->code }}</td>
                        <td>{{ $garage->type->label() }}</td>
                        <td class="text-charcoal-500">#{{ $garage->garage_batch_id }}</td>
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
                    <tr><td colspan="4" class="py-10 text-center text-charcoal-500">Belum ada garasi. Buat kelompok garasi pertama.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $garages->links() }}</div>
</x-layouts.admin>
