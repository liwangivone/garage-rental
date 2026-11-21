<x-layouts.customer title="Beranda" :subtitle="'Halo, '.$user->name">
    @if ($user->registration_status === \App\Enums\RegistrationStatus::Pending)
        <div class="mb-6 border-l-4 border-ember-500 bg-ember-100 px-4 py-3 text-sm text-charcoal-800">
            <strong class="font-semibold uppercase tracking-wider">Menunggu verifikasi.</strong>
            Admin sedang memeriksa data KTP Anda. Setelah diterima, Anda bisa menyewa garasi.
        </div>
    @elseif ($user->registration_status === \App\Enums\RegistrationStatus::Rejected)
        <div class="mb-6 border-l-4 border-brick bg-brick/10 px-4 py-3 text-sm text-charcoal-800">
            <strong class="font-semibold uppercase tracking-wider">Pendaftaran ditolak.</strong>
            @if ($user->rejection_reason) Alasan: {{ $user->rejection_reason }}. @endif
        </div>
        <div class="mb-6">
            <a href="{{ route('penyewa.profile.edit') }}" class="btn btn-primary">Perbaiki data & ajukan ulang</a>
        </div>
    @endif

    @if ($rejectedCount > 0)
        <div class="mb-6 border-l-4 border-brick bg-brick/10 px-4 py-3 text-sm">
            {{ $rejectedCount }} sewa ditolak pembayarannya.
            <a href="{{ route('penyewa.rentals.index') }}" class="font-semibold text-brick underline">Lihat di Sewa Saya</a>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Sewa berlangsung" :value="$active->count()" accent />
        <x-stat label="Total sewa" :value="$totalRentals" />
        <x-stat label="Kendaraan terdaftar" :value="$vehicleCount" />
    </div>

    @if ($user->isApproved())
        <div class="mt-6">
            <a href="{{ route('penyewa.rentals.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Sewa garasi baru</a>
        </div>
    @endif

    <section class="mt-8">
        <h2 class="card-title mb-3">Sedang berlangsung</h2>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($active as $rental)
                @include('penyewa.rentals._card', ['rental' => $rental])
            @empty
                <p class="card col-span-full px-5 py-8 text-center text-sm text-charcoal-500">Tidak ada sewa yang sedang berlangsung.</p>
            @endforelse
        </div>
    </section>

    @if ($upcoming->isNotEmpty())
        <section class="mt-8">
            <h2 class="card-title mb-3">Akan datang</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($upcoming as $rental)
                    @include('penyewa.rentals._card', ['rental' => $rental])
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.customer>
