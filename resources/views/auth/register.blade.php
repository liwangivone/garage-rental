<x-layouts.guest title="Daftar Penyewa" width="max-w-md">
    <h1 class="mt-6 text-2xl font-semibold">Daftar penyewa</h1>
    <p class="mt-1 text-sm text-charcoal-500">Data KTP Anda akan diverifikasi admin sebelum bisa menyewa garasi.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="nik" class="label">NIK (Nomor KTP)</label>
            <input id="nik" name="nik" inputmode="numeric" maxlength="16" value="{{ old('nik') }}" required autofocus class="input" placeholder="16 digit">
            @error('nik')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="label">Nama lengkap</label>
            <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="input">
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone" class="label">Nomor telepon</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" class="input">
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="label">Email (username)</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="input">
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="input">
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="label">Ulangi password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
            </div>
        </div>
        <button class="btn btn-primary w-full">Daftar</button>
    </form>
    <p class="mt-6 text-center text-sm text-charcoal-600">Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-ember-600 hover:underline">Masuk</a></p>
</x-layouts.guest>
