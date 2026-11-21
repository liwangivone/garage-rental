<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalExtensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExtensionController extends Controller
{
    /** Daftar garasi yang sedang disewa hari ini. */
    public function index(): View
    {
        $rentals = Rental::query()->with(['penyewa', 'garage', 'vehicle'])
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())
            ->orderBy('end_date')->paginate(20);

        return view('admin.extensions.index', ['rentals' => $rentals]);
    }

    public function show(Request $request, Rental $rental, RentalExtensionService $service): View
    {
        $this->ensureActive($rental);
        $rental->load(['penyewa', 'garage.batch', 'vehicle', 'extensions.admin']);

        $preview = $error = $newEnd = null;
        if ($request->filled('new_end_date')) {
            $request->validate(['new_end_date' => ['date']]);
            $newEnd = Carbon::parse($request->query('new_end_date'))->startOfDay();
            $error = $rental->extensionError($newEnd);
            $preview = $error ? null : $service->preview($rental, $newEnd);
        }

        return view('admin.extensions.show', compact('rental', 'preview', 'error', 'newEnd'));
    }

    public function update(Request $request, Rental $rental, RentalExtensionService $service): RedirectResponse
    {
        $this->ensureActive($rental);

        $data = $request->validate([
            'new_end_date' => ['required', 'date'],
            'payment_proof' => ['required', 'image', 'max:4096'],
        ]);

        $newEnd = Carbon::parse($data['new_end_date'])->startOfDay();
        if ($error = $rental->extensionError($newEnd)) {
            throw ValidationException::withMessages(['new_end_date' => $error]);
        }

        $service->extend($rental, $newEnd, $request->file('payment_proof'), $request->user());

        return redirect()->route('admin.extensions.index')
            ->with('status', "Sewa garasi {$rental->garage->code} diperpanjang sampai {$newEnd->translatedFormat('j F Y')}.");
    }

    private function ensureActive(Rental $rental): void
    {
        abort_unless($rental->isActiveOn(), 404);
    }
}
