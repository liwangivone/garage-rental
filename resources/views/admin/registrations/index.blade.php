<x-layouts.admin title="Verifikasi Pendaftar" subtitle="Cek validasi KTP, lalu terima atau tolak pendaftaran penyewa baru">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (\App\Enums\RegistrationStatus::cases() as $s)
            <a href="{{ route('admin.registrations.index', ['status' => $s->value]) }}" @class(['btn btn-sm', 'btn-dark' => $status === $s, 'btn-ghost' => $status !== $s])>
                {{ ['pending' => 'Menunggu', 'approved' => 'Diterima', 'rejected' => 'Ditolak'][$s->value] }}
                <span class="opacity-70">({{ $counts[$s->value] ?? 0 }})</span>
            </a>
        @endforeach
    </div>

    <div class="card table-wrap">
        <table class="table">
            <thead>
                <tr><th>Nama</th><th>NIK</th><th>Telepon</th><th>Email</th><th>Daftar</th><th class="text-right">{{ $status->value === 'pending' ? 'Aksi' : 'Keterangan' }}</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="font-medium">{{ $user->name }}</td>
                        <td class="font-mono text-xs">{{ $user->nik }}</td>
                        <td>{{ $user->phone }}</td>
                        <td>{{ $user->email }}</td>
                        <td class="whitespace-nowrap text-charcoal-500">{{ $user->created_at->translatedFormat('j M Y') }}</td>
                        <td class="text-right">
                            @if ($status === \App\Enums\RegistrationStatus::Pending)
                                <div class="flex flex-wrap items-start justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.registrations.approve', $user) }}">@csrf
                                        <button class="btn btn-primary btn-sm">Terima</button>
                                    </form>
                                    <details class="relative">
                                        <summary class="btn btn-danger btn-sm list-none">Tolak</summary>
                                        <form method="POST" action="{{ route('admin.registrations.reject', $user) }}" class="absolute right-0 z-10 mt-2 w-64 space-y-2 border border-cream-300 bg-cream-50 p-3 text-left shadow-lg">
                                            @csrf
                                            <label class="label" for="reason-{{ $user->id }}">Alasan (opsional)</label>
                                            <textarea id="reason-{{ $user->id }}" name="rejection_reason" rows="2" maxlength="500" class="input" placeholder="mis. KTP tidak terbaca"></textarea>
                                            <button class="btn btn-danger btn-sm w-full">Konfirmasi tolak</button>
                                        </form>
                                    </details>
                                </div>
                            @else
                                <span class="text-xs text-charcoal-500">
                                    {{ $user->verified_at?->translatedFormat('j M Y') }}
                                    @if ($user->verifier) · {{ $user->verifier->name }} @endif
                                    @if ($user->rejection_reason)<br>“{{ $user->rejection_reason }}”@endif
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-charcoal-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>
