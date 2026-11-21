<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>Masuk · Garage Rental</title>
</head>
<body class="flex min-h-screen items-center justify-center bg-charcoal-900 px-4">
    <div class="w-full max-w-sm">
        <div class="hazard h-2"></div>
        <div class="bg-cream-50 px-8 py-10">
            <p class="font-display text-3xl font-bold uppercase leading-none tracking-wider text-charcoal-900">Garage<span class="text-ember-500">Rental</span></p>
            <p class="mt-1 text-xs uppercase tracking-[0.2em] text-charcoal-500">Panel Admin</p>

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
        </div>
    </div>
</body>
</html>
