<?php

namespace App\Http\Controllers\Penyewa;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Penyewa yang pendaftarannya ditolak dapat memperbaiki data dan mengajukan ulang. */
class ProfileController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->user()->registration_status !== RegistrationStatus::Rejected) {
            return redirect()->route('penyewa.dashboard');
        }

        return view('penyewa.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->registration_status === RegistrationStatus::Rejected, 403);

        $data = $request->validate([
            'nik' => ['required', 'digits:16', Rule::unique('users', 'nik')->ignore($user->id)],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{8,20}$/'],
        ]);

        $user->update([
            ...$data,
            'registration_status' => RegistrationStatus::Pending,
            'rejection_reason' => null,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return redirect()->route('penyewa.dashboard')->with('status', 'Data diperbarui. Admin akan memverifikasi ulang pendaftaran Anda.');
    }
}
