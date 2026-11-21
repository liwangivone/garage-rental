<x-layouts.admin title="Dashboard" :subtitle="now()->translatedFormat('l, j F Y')">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Sewa aktif hari ini" :value="$activeCount" accent />
        <x-stat label="Pendaftar menunggu" :value="$pendingCount" hint="Perlu verifikasi" :accent="$pendingCount > 0" />
        <x-stat label="Pendapatan bulan ini" :value="\App\Support\Format::rupiah($monthRevenue)" hint="Berdasarkan tanggal mulai sewa" />
        <x-stat label="Total garasi" :value="$types->sum('total')" />
    </div>

    <section class="mt-8">
        <h2 class="card-title mb-3">Okupansi hari ini</h2>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($types as $row)
                @php $pct = $row['total'] > 0 ? round($row['used'] / $row['total'] * 100) : 0; @endphp
                <div class="card p-5">
                    <div class="flex items-baseline justify-between">
                        <p class="text-sm font-semibold uppercase tracking-wider text-charcoal-700">{{ $row['type']->label() }}</p>
                        <p class="font-display text-2xl font-semibold">{{ $row['used'] }}<span class="text-base text-charcoal-400"> / {{ $row['total'] }}</span></p>
                    </div>
                    <div class="mt-3 h-2 bg-cream-200"><div class="h-2 bg-ember-500" style="width: {{ $pct }}%"></div></div>
                    <p class="mt-2 text-xs text-charcoal-500">{{ $pct }}% terpakai · {{ $row['total'] - $row['used'] }} kosong</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="flex items-center justify-between border-b border-cream-200 px-5 py-3">
                <h2 class="card-title">Segera berakhir (3 hari)</h2>
                <a href="{{ route('admin.extensions.index') }}" class="text-xs font-semibold uppercase tracking-wider text-ember-600 hover:underline">Semua</a>
            </div>
            @forelse ($endingSoon as $rental)
                <a href="{{ route('admin.extensions.show', $rental) }}" class="flex items-center justify-between border-b border-cream-200 px-5 py-3 last:border-0 hover:bg-cream-100">
                    <div>
                        <p class="text-sm font-medium">{{ $rental->garage->code }} · {{ $rental->penyewa->name }}</p>
                        <p class="text-xs text-charcoal-500">{{ $rental->vehicle->brand }} {{ $rental->vehicle->model }}</p>
                    </div>
                    <span class="badge badge-ember">{{ $rental->end_date->translatedFormat('j M') }}</span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-charcoal-500">Tidak ada sewa yang berakhir dalam 3 hari.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="flex items-center justify-between border-b border-cream-200 px-5 py-3">
                <h2 class="card-title">Pendaftar baru</h2>
                <a href="{{ route('admin.registrations.index') }}" class="text-xs font-semibold uppercase tracking-wider text-ember-600 hover:underline">Verifikasi</a>
            </div>
            @forelse ($pending as $user)
                <div class="flex items-center justify-between border-b border-cream-200 px-5 py-3 last:border-0">
                    <div>
                        <p class="text-sm font-medium">{{ $user->name }}</p>
                        <p class="text-xs text-charcoal-500">NIK {{ $user->nik }}</p>
                    </div>
                    <span class="text-xs text-charcoal-500">{{ $user->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-charcoal-500">Tidak ada pendaftar yang menunggu.</p>
            @endforelse
        </section>
    </div>
</x-layouts.admin>
