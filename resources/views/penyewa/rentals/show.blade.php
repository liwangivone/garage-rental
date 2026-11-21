<x-layouts.customer :title="'Sewa '.$rental->garage->code" :subtitle="$rental->garage->type->label()">
    <x-slot:actions><a href="{{ route('penyewa.rentals.index') }}" class="btn btn-ghost btn-sm">← Kembali</a></x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="card-title">Detail sewa</h2>
                    @include('penyewa.rentals._phase', ['rental' => $rental])
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                    <div><dt class="label">Garasi</dt><dd class="font-display text-xl font-semibold tracking-wider">{{ $rental->garage->code }}</dd></div>
                    <div><dt class="label">Tipe sewa</dt><dd>{{ $rental->rental_type === \App\Enums\RentalType::Monthly ? 'Bulanan' : 'Harian' }}
                        <span class="text-charcoal-500">({{ $rental->duration_days }} hari)</span></dd></div>
                    <div><dt class="label">Mulai</dt><dd>{{ $rental->start_date->translatedFormat('l, j F Y') }}</dd></div>
                    <div><dt class="label">Selesai</dt><dd>{{ $rental->end_date->translatedFormat('l, j F Y') }}</dd></div>
                    <div><dt class="label">Kendaraan</dt><dd>{{ $rental->vehicle->type->label() }} · {{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}</dd></div>
                    <div><dt class="label">Nomor plat</dt><dd>{{ $rental->vehicle->plate_number ?? '—' }}</dd></div>
                    @if ($rental->vehicle->battery_capacity_kwh)
                        <div><dt class="label">Kapasitas baterai</dt><dd>{{ rtrim(rtrim($rental->vehicle->battery_capacity_kwh, '0'), '.') }} kWh</dd></div>
                    @endif
                </dl>
            </section>

            @if ($rental->extensions->isNotEmpty())
                <section class="card p-5">
                    <h2 class="card-title mb-3">Perpanjangan</h2>
                    <ul class="divide-y divide-cream-200 text-sm">
                        @foreach ($rental->extensions as $ext)
                            <li class="flex justify-between py-2">
                                <span>{{ $ext->previous_end_date->translatedFormat('j M Y') }} → <strong>{{ $ext->new_end_date->translatedFormat('j M Y') }}</strong></span>
                                <span>+ @rupiah($ext->additional_cost)</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            <section class="card p-5">
                <h2 class="card-title mb-3">Rincian biaya</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-charcoal-500">Biaya sewa</dt><dd>@rupiah($rental->rental_cost)</dd></div>
                    @if ($rental->electricity_cost > 0)
                        <div class="flex justify-between"><dt class="text-charcoal-500">Isi listrik</dt><dd>@rupiah($rental->electricity_cost)</dd></div>
                    @endif
                    <div class="flex items-baseline justify-between border-t border-cream-300 pt-2">
                        <dt class="font-semibold uppercase tracking-wider">Total</dt>
                        <dd class="font-display text-2xl font-semibold text-ember-600">@rupiah($rental->total_price)</dd>
                    </div>
                </dl>
            </section>
            <section class="card p-5">
                <h2 class="card-title mb-3">Bukti pelunasan</h2>
                <a href="{{ Storage::disk('public')->url($rental->payment_proof_path) }}" target="_blank" class="block">
                    <img src="{{ Storage::disk('public')->url($rental->payment_proof_path) }}" alt="Bukti pelunasan" class="max-h-64 w-full border border-cream-200 object-contain bg-white">
                </a>
            </section>
            <p class="text-xs text-charcoal-500">Untuk perpanjangan sewa, hubungi admin.</p>
        </div>
    </div>
</x-layouts.customer>
