<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nik' => ['required', 'digits:16', 'unique:users,nik'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            ...$data,
            'role' => UserRole::Penyewa,
            'registration_status' => RegistrationStatus::Pending,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('penyewa.dashboard')
            ->with('status', 'Pendaftaran berhasil. Admin akan memverifikasi data KTP Anda.');
    }
}
