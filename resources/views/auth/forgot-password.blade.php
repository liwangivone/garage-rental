<x-layouts.guest title="Lupa Password">
    <h1 class="mt-6 text-2xl font-semibold">Lupa password</h1>
    <p class="mt-1 text-sm text-charcoal-500">Masukkan email Anda. Kami kirimkan tautan untuk membuat password baru.</p>
    @if (session('status'))<p class="mt-4 border-l-4 border-olive bg-olive/10 px-3 py-2 text-sm">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary w-full">Kirim tautan reset</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-ember-600 hover:underline">Kembali ke login</a></p>
</x-layouts.guest>
