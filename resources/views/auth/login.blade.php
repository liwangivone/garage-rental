<x-layouts.guest title="Masuk">
    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-charcoal-600">
            <input type="checkbox" name="remember" class="accent-ember-500"> Ingat saya
        </label>
        <button class="btn btn-primary w-full">Masuk</button>
    </form>
    <p class="mt-6 text-center text-sm text-charcoal-600">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-ember-600 hover:underline">Daftar sebagai penyewa</a></p>
</x-layouts.guest>
