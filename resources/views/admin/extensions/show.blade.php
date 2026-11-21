<x-layouts.admin :title="'Perpanjang '.$rental->garage->code" subtitle="Ubah tanggal selesai, cek sisa biaya, lalu unggah bukti bayar">
    <x-slot:actions><a href="{{ route('admin.extensions.index') }}" class="btn btn-ghost btn-sm">← Kembali</a></x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <section class="card p-5">
                <h2 class="card-title mb-3">Sewa saat ini</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div><dt class="label">Penyewa</dt><dd>{{ $rental->penyewa->name }}</dd></div>
                    <div><dt class="label">Garasi</dt><dd>{{ $rental->garage->code }} · {{ $rental->garage->type->label() }}</dd></div>
                    <div><dt class="label">Kendaraan</dt><dd>{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }} {{ $rental->vehicle->plate_number ? '('.$rental->vehicle->plate_number.')' : '' }}</dd></div>
                    <div><dt class="label">Harga harian</dt><dd>@rupiah($rental->daily_price)</dd></div>
                    <div><dt class="label">Mulai</dt><dd>{{ $rental->start_date->translatedFormat('j F Y') }}</dd></div>
                    <div><dt class="label">Selesai</dt><dd>{{ $rental->end_date->translatedFormat('j F Y') }}</dd></div>
                    <div><dt class="label">Total saat ini</dt><dd class="font-semibold">@rupiah($rental->total_price)</dd></div>
                </dl>
            </section>

            @if ($rental->extensions->isNotEmpty())
                <section class="card p-5">
                    <h2 class="card-title mb-3">Riwayat perpanjangan</h2>
                    <ul class="divide-y divide-cream-200 text-sm">
                        @foreach ($rental->extensions as $ext)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span>{{ $ext->previous_end_date->translatedFormat('j M Y') }} → <strong>{{ $ext->new_end_date->translatedFormat('j M Y') }}</strong>
                                    <span class="text-xs text-charcoal-500">oleh {{ $ext->admin->name }}</span></span>
                                <span>+ @rupiah($ext->additional_cost)
                                    <a href="{{ Storage::disk('public')->url($ext->payment_proof_path) }}" target="_blank" class="ml-2 text-xs font-semibold uppercase text-ember-600 hover:underline">Bukti</a></span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <form method="GET" class="card space-y-3 p-5">
                <h2 class="card-title">1. Tanggal selesai baru</h2>
                <div>
                    <label for="new_end_date_q" class="label">Tanggal selesai baru</label>
                    <input id="new_end_date_q" type="date" name="new_end_date" value="{{ $newEnd?->toDateString() ?? request('new_end_date') }}" min="{{ $rental->end_date->copy()->addDay()->toDateString() }}" required class="input">
                    @error('new_end_date')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-dark w-full">Hitung biaya</button>
            </form>

            @if ($error)
                <div class="border-l-4 border-brick bg-brick/10 px-4 py-3 text-sm" role="alert">{{ $error }}</div>
            @endif

            @if ($preview)
                <form method="POST" action="{{ route('admin.extensions.update', $rental) }}" enctype="multipart/form-data" class="card space-y-4 p-5">
                    @csrf
                    <input type="hidden" name="new_end_date" value="{{ $newEnd->toDateString() }}">
                    <h2 class="card-title">2. Rincian & bukti bayar</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-charcoal-500">Periode baru</dt><dd>{{ $preview['duration_days'] }} hari</dd></div>
                        <div class="flex justify-between"><dt class="text-charcoal-500">Rincian</dt><dd>
                            {{ $preview['breakdown']['monthly'] ? $preview['breakdown']['monthly'].' bulan' : '' }}{{ $preview['breakdown']['monthly'] && $preview['breakdown']['daily'] ? ' + ' : '' }}{{ $preview['breakdown']['daily'] ? $preview['breakdown']['daily'].' hari' : '' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-charcoal-500">Total lama</dt><dd>@rupiah($preview['previous_total'])</dd></div>
                        <div class="flex justify-between"><dt class="text-charcoal-500">Total baru</dt><dd>@rupiah($preview['new_total'])</dd></div>
                        <div class="flex items-baseline justify-between border-t border-cream-300 pt-2"><dt class="font-semibold uppercase tracking-wider">Sisa dibayar</dt>
                            <dd class="font-display text-2xl font-semibold text-ember-600">@rupiah($preview['additional_cost'])</dd></div>
                    </dl>
                    <div>
                        <label for="payment_proof" class="label">Bukti bayar (gambar)</label>
                        <input id="payment_proof" type="file" name="payment_proof" accept="image/*" required class="input file:mr-3 file:border-0 file:bg-charcoal-800 file:px-3 file:py-1 file:text-xs file:font-semibold file:uppercase file:text-cream-50">
                        @error('payment_proof')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn-primary w-full">Simpan perpanjangan</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.admin>
