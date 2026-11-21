@php use App\Enums\PaymentStatus; @endphp
<x-layouts.admin :title="'Sewa '.$rental->garage->code" :subtitle="$rental->penyewa->name">
    <x-slot:actions><a href="{{ route('admin.rentals.index') }}" class="btn btn-ghost btn-sm">← Kembali</a></x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <section class="card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="card-title">Detail sewa</h2>
                    <x-payment-badge :status="$rental->payment_status" />
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div><dt class="label">Penyewa</dt><dd>{{ $rental->penyewa->name }}<span class="block text-xs text-charcoal-500">{{ $rental->penyewa->phone }}</span></dd></div>
                    <div><dt class="label">Garasi</dt><dd>{{ $rental->garage->code }} · {{ $rental->garage->type->label() }}</dd></div>
                    <div><dt class="label">Kendaraan</dt><dd>{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }} {{ $rental->vehicle->plate_number ? '('.$rental->vehicle->plate_number.')' : '' }}</dd></div>
                    <div><dt class="label">Harga harian (saat transaksi)</dt><dd>@rupiah($rental->daily_price)</dd></div>
                    <div><dt class="label">Mulai</dt><dd>{{ $rental->start_date->translatedFormat('j F Y') }}</dd></div>
                    <div><dt class="label">Selesai</dt><dd>{{ $rental->end_date->translatedFormat('j F Y') }}</dd></div>
                    <div><dt class="label">Biaya sewa</dt><dd>@rupiah($rental->rental_cost)</dd></div>
                    @if ($rental->battery_charge_kwh !== null)
                        <div><dt class="label">Sisa baterai saat sewa</dt><dd>{{ rtrim(rtrim($rental->battery_current_kwh, '0'), '.') ?: '0' }} kWh (isi {{ rtrim(rtrim($rental->battery_charge_kwh, '0'), '.') ?: '0' }} kWh)</dd></div>
                    @endif
                    <div><dt class="label">Biaya listrik</dt><dd>@rupiah($rental->electricity_cost)</dd></div>
                    <div class="col-span-2"><dt class="label">Total</dt><dd class="font-display text-2xl font-semibold text-ember-600">@rupiah($rental->total_price)</dd></div>
                </dl>
            </section>

            <section class="card p-5">
                <h2 class="card-title mb-3">Bukti pembayaran</h2>
                <a href="{{ Storage::disk('public')->url($rental->payment_proof_path) }}" target="_blank">
                    <img src="{{ Storage::disk('public')->url($rental->payment_proof_path) }}" alt="Bukti pembayaran" class="max-h-96 w-full border border-cream-200 bg-white object-contain">
                </a>

                @if ($rental->payment_status === PaymentStatus::Pending)
                    <div class="mt-4 flex flex-wrap items-start gap-2">
                        <form method="POST" action="{{ route('admin.rentals.approve', $rental) }}">@csrf
                            <button class="btn btn-primary">Verifikasi pembayaran</button>
                        </form>
                        <details class="relative">
                            <summary class="btn btn-danger list-none">Tolak</summary>
                            <form method="POST" action="{{ route('admin.rentals.reject', $rental) }}" class="absolute left-0 z-10 mt-2 w-72 space-y-2 border border-cream-300 bg-cream-50 p-3 shadow-lg">
                                @csrf
                                <label class="label" for="reason">Alasan (opsional)</label>
                                <textarea id="reason" name="rejection_reason" rows="2" maxlength="500" class="input" placeholder="mis. nominal tidak sesuai"></textarea>
                                <button class="btn btn-danger btn-sm w-full">Konfirmasi tolak</button>
                            </form>
                        </details>
                    </div>
                    <p class="mt-2 text-xs text-charcoal-500">Selama belum diverifikasi, garasi tetap ditahan. Bila ditolak, garasi dilepas.</p>
                @else
                    <p class="mt-3 text-xs text-charcoal-500">
                        {{ $rental->payment_status->label() }} {{ $rental->payment_verified_at?->translatedFormat('j M Y H:i') }}
                        @if ($rental->paymentVerifier) oleh {{ $rental->paymentVerifier->name }}@endif
                        @if ($rental->payment_rejection_reason) · “{{ $rental->payment_rejection_reason }}”@endif
                    </p>
                @endif
            </section>

            @if ($rental->extensions->isNotEmpty())
                <section class="card p-5">
                    <h2 class="card-title mb-3">Riwayat perpanjangan</h2>
                    <ul class="divide-y divide-cream-200 text-sm">
                        @foreach ($rental->extensions as $ext)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span>{{ $ext->previous_end_date->translatedFormat('j M Y') }} → <strong>{{ $ext->new_end_date->translatedFormat('j M Y') }}</strong>
                                    <span class="text-xs text-charcoal-500">oleh {{ $ext->admin->name }}</span></span>
                                <span>{{ $ext->additional_cost < 0 ? '−' : '+' }} @rupiah(abs($ext->additional_cost))
                                    @if ($ext->electricity_cost > 0)<span class="text-xs text-charcoal-500">(listrik @rupiah($ext->electricity_cost))</span>@endif
                                    <a href="{{ Storage::disk('public')->url($ext->payment_proof_path) }}" target="_blank" class="ml-2 text-xs font-semibold uppercase text-ember-600 hover:underline">Bukti</a></span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <section>
                <h2 class="card-title mb-3">Perpanjang sewa</h2>
                @if (! $canExtend)
                    <div class="card p-5 text-sm text-charcoal-600">{{ $extendBlockedReason }}</div>
                @else
                    <form method="GET" class="card space-y-3 p-5">
                        <div>
                            <label for="new_end_date_q" class="label">1. Tanggal selesai baru</label>
                            <input id="new_end_date_q" type="date" name="new_end_date" value="{{ $newEnd?->toDateString() ?? request('new_end_date') }}" min="{{ $rental->end_date->copy()->addDay()->toDateString() }}" required class="input">
                            @error('new_end_date')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        @if ($electricityApplicable)
                            <div>
                                <label for="extra_electricity_q" class="label">Biaya listrik tambahan (Rp, opsional)</label>
                                <input id="extra_electricity_q" type="number" min="0" step="1" name="extra_electricity" value="{{ request('extra_electricity') }}" class="input" placeholder="0">
                                <p class="mt-1 text-xs text-charcoal-500">Isi bila mobil mengisi listrik lagi selama perpanjangan.</p>
                            </div>
                        @endif
                        <button class="btn btn-dark w-full">Hitung biaya</button>
                    </form>

                    @if ($error)
                        <div class="mt-4 border-l-4 border-brick bg-brick/10 px-4 py-3 text-sm" role="alert">{{ $error }}</div>
                    @endif

                    @if ($preview)
                        <form method="POST" action="{{ route('admin.rentals.extend', $rental) }}" enctype="multipart/form-data" class="card mt-4 space-y-4 p-5">
                            @csrf
                            <input type="hidden" name="new_end_date" value="{{ $newEnd->toDateString() }}">
                            <input type="hidden" name="extra_electricity" value="{{ $preview['extra_electricity'] }}">
                            <h3 class="card-title">2. Rincian & bukti bayar</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-charcoal-500">Periode baru</dt><dd>{{ $preview['duration_days'] }} hari</dd></div>
                                <div class="flex justify-between"><dt class="text-charcoal-500">Rincian</dt><dd>
                                    {{ $preview['breakdown']['monthly'] ? $preview['breakdown']['monthly'].' bulan' : '' }}{{ $preview['breakdown']['monthly'] && $preview['breakdown']['daily'] ? ' + ' : '' }}{{ $preview['breakdown']['daily'] ? $preview['breakdown']['daily'].' hari' : '' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-charcoal-500">Total lama</dt><dd>@rupiah($preview['previous_total'])</dd></div>
                                @if ($preview['extra_electricity'] > 0)
                                    <div class="flex justify-between"><dt class="text-charcoal-500">Listrik tambahan</dt><dd>@rupiah($preview['extra_electricity'])</dd></div>
                                @endif
                                <div class="flex justify-between"><dt class="text-charcoal-500">Total baru</dt><dd>@rupiah($preview['new_total'])</dd></div>
                                <div class="flex items-baseline justify-between border-t border-cream-300 pt-2"><dt class="font-semibold uppercase tracking-wider">Sisa dibayar</dt>
                                    <dd class="font-display text-2xl font-semibold text-ember-600">{{ $preview['additional_cost'] < 0 ? '−' : '' }}@rupiah(abs($preview['additional_cost']))</dd></div>
                            </dl>
                            <div>
                                <label for="payment_proof" class="label">Bukti bayar (gambar)</label>
                                <input id="payment_proof" type="file" name="payment_proof" accept="image/png,image/jpeg,image/webp" required class="input file:mr-3 file:border-0 file:bg-charcoal-800 file:px-3 file:py-1 file:text-xs file:font-semibold file:uppercase file:text-cream-50">
                                @error('payment_proof')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <button class="btn btn-primary w-full">Simpan perpanjangan</button>
                        </form>
                    @endif
                @endif
            </section>

            <section class="card border-brick/30 p-5">
                <h2 class="card-title mb-2 text-brick">Hapus sewa</h2>
                <p class="text-sm text-charcoal-600">Untuk pembatalan yang diproses di luar sistem (termasuk refund). Data sewa, bukti bayar, dan riwayat perpanjangan dihapus permanen, dan garasi dilepas.</p>
                <form method="POST" action="{{ route('admin.rentals.destroy', $rental) }}" class="mt-3" onsubmit="return confirm('Hapus sewa ini secara permanen?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm">Hapus sewa</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.admin>
