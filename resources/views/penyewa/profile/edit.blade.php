<x-layouts.customer title="Perbaiki Data" subtitle="Perbarui data Anda, lalu ajukan ulang untuk diverifikasi admin">
    @if ($user->rejection_reason)
        <div class="mb-6 max-w-xl border-l-4 border-brick bg-brick/10 px-4 py-3 text-sm">Alasan penolakan: {{ $user->rejection_reason }}</div>
    @endif
    <form method="POST" action="{{ route('penyewa.profile.update') }}" class="card max-w-xl space-y-4 p-6">
        @csrf @method('PUT')
        <div>
            <label for="nik" class="label">NIK (Nomor KTP)</label>
            <input id="nik" name="nik" inputmode="numeric" maxlength="16" value="{{ old('nik', $user->nik) }}" required class="input">
            @error('nik')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="label">Nama lengkap</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input">
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone" class="label">Nomor telepon</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" required class="input">
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button class="btn btn-primary">Ajukan ulang</button>
            <a href="{{ route('penyewa.dashboard') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.customer>
