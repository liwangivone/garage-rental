<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalExtensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RentalController extends Controller
{
    public const FILTERS = [
        'pending' => 'Menunggu verifikasi',
        'active' => 'Berlangsung',
        'upcoming' => 'Akan datang',
        'finished' => 'Selesai',
        'rejected' => 'Ditolak',
        'all' => 'Semua',
    ];

    public function index(Request $request): View
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'pending';

        $query = Rental::query()->with(['penyewa', 'garage', 'vehicle']);
        match ($filter) {
            'pending' => $query->where('payment_status', PaymentStatus::Pending->value)->orderBy('created_at'),
            'rejected' => $query->where('payment_status', PaymentStatus::Rejected->value)->latest('id'),
            'active' => $query->approved()->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->orderBy('end_date'),
            'upcoming' => $query->approved()->whereDate('start_date', '>', today())->orderBy('start_date'),
            'finished' => $query->approved()->whereDate('end_date', '<', today())->orderByDesc('end_date'),
            default => $query->latest('id'),
        };

        $counts = Rental::query()->selectRaw('payment_status, count(*) as total')->groupBy('payment_status')->pluck('total', 'payment_status');

        return view('admin.rentals.index', [
            'rentals' => $query->paginate(20)->withQueryString(),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'pendingCount' => $counts[PaymentStatus::Pending->value] ?? 0,
        ]);
    }

    public function show(Request $request, Rental $rental, RentalExtensionService $service): View
    {
        $rental->load(['penyewa', 'garage', 'vehicle', 'extensions.admin', 'paymentVerifier']);

        $preview = $error = $newEnd = null;
        $canExtend = $rental->extendableError() === null;
        if ($canExtend && $request->filled('new_end_date')) {
            $request->validate([
                'new_end_date' => ['date'],
                'extra_electricity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            ]);
            $newEnd = Carbon::parse($request->query('new_end_date'))->startOfDay();
            $error = $rental->extensionError($newEnd);
            $preview = $error ? null : $service->preview($rental, $newEnd, (string) ($request->query('extra_electricity') ?: 0));
        }

        return view('admin.rentals.show', [
            'rental' => $rental,
            'preview' => $preview,
            'error' => $error,
            'newEnd' => $newEnd,
            'canExtend' => $canExtend,
            'extendBlockedReason' => $rental->extendableError(),
            'electricityApplicable' => $rental->electricity_price_per_kwh !== null,
        ]);
    }

    public function approve(Request $request, Rental $rental): RedirectResponse
    {
        return $this->decide($request, $rental, PaymentStatus::Approved);
    }

    public function reject(Request $request, Rental $rental): RedirectResponse
    {
        $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);

        return $this->decide($request, $rental, PaymentStatus::Rejected, $request->input('rejection_reason'));
    }

    private function decide(Request $request, Rental $rental, PaymentStatus $status, ?string $reason = null): RedirectResponse
    {
        if ($rental->payment_status !== PaymentStatus::Pending) {
            return back()->with('error', 'Pembayaran sewa ini sudah diverifikasi.');
        }

        $rental->update([
            'payment_status' => $status,
            'payment_rejection_reason' => $reason,
            'payment_verified_at' => now(),
            'payment_verified_by' => $request->user()->id,
        ]);
        $rental->garage->syncStatus(); // sewa yang ditolak melepas garasi

        return back()->with('status', $status === PaymentStatus::Approved ? 'Pembayaran diverifikasi.' : 'Pembayaran ditolak, garasi dilepas.');
    }

    public function extend(Request $request, Rental $rental, RentalExtensionService $service): RedirectResponse
    {
        $data = $request->validate([
            'new_end_date' => ['required', 'date'],
            'extra_electricity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $newEnd = Carbon::parse($data['new_end_date'])->startOfDay();
        if ($error = $rental->extensionError($newEnd)) {
            throw ValidationException::withMessages(['new_end_date' => $error]);
        }

        $service->extend($rental, $newEnd, $request->file('payment_proof'), $request->user(), (string) ($data['extra_electricity'] ?? 0));

        return redirect()->route('admin.rentals.show', $rental)
            ->with('status', "Sewa garasi {$rental->garage->code} diperpanjang sampai {$newEnd->translatedFormat('j F Y')}.");
    }

    /** Pembatalan dilakukan di luar sistem; admin menghapus datanya di sini. */
    public function destroy(Rental $rental): RedirectResponse
    {
        $garage = $rental->garage;
        $files = array_filter([$rental->payment_proof_path, ...$rental->extensions->pluck('payment_proof_path')->all()]);

        DB::transaction(fn () => $rental->delete());
        $garage->syncStatus();
        collect($files)->each(fn ($f) => Storage::disk('public')->delete($f));

        return redirect()->route('admin.rentals.index')->with('status', "Sewa garasi {$garage->code} dihapus.");
    }
}
