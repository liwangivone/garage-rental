<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $status = RegistrationStatus::tryFrom((string) $request->query('status')) ?? RegistrationStatus::Pending;

        return view('admin.registrations.index', [
            'status' => $status,
            'users' => User::penyewa()->where('registration_status', $status)->latest()->paginate(15)->withQueryString(),
            'counts' => User::penyewa()->selectRaw('registration_status, count(*) as total')->groupBy('registration_status')->pluck('total', 'registration_status'),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, RegistrationStatus::Approved);
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);

        return $this->decide($request, $user, RegistrationStatus::Rejected, $request->input('rejection_reason'));
    }

    private function decide(Request $request, User $user, RegistrationStatus $status, ?string $reason = null): RedirectResponse
    {
        abort_unless($user->isPenyewa(), 404);

        if ($user->registration_status !== RegistrationStatus::Pending) {
            return back()->with('error', 'Pendaftaran ini sudah diproses.');
        }

        $user->update([
            'registration_status' => $status,
            'rejection_reason' => $reason,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        return back()->with('status', "Pendaftaran {$user->name} ".($status === RegistrationStatus::Approved ? 'diterima.' : 'ditolak.'));
    }
}
