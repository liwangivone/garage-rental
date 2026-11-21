<x-layouts.guest title="Reset Password">
    <h1 class="mt-6 text-2xl font-semibold">Password baru</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="input">
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="label">Password baru</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="input">
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="label">Ulangi password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
        </div>
        <button class="btn btn-primary w-full">Simpan password</button>
    </form>
</x-layouts.guest>
