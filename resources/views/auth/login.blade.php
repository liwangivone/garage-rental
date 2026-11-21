<x-layouts.guest title="Masuk">
    @if (session('status'))<p class="mt-6 border-l-4 border-olive bg-olive/10 px-3 py-2 text-sm">{{ session('status') }}</p>@endif
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
        <div class="flex items-center justify-between text-sm text-charcoal-600">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="accent-ember-500"> Ingat saya</label>
            <a href="{{ route('password.request') }}" class="font-semibold text-ember-600 hover:underline">Lupa password?</a>
        </div>
        <button class="btn btn-primary w-full">Masuk</button>
    </form>
    <p class="mt-6 text-center text-sm text-charcoal-600">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-ember-600 hover:underline">Daftar sebagai penyewa</a></p>
</x-layouts.guest>
