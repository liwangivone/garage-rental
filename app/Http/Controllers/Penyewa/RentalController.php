<?php

namespace App\Http\Controllers\Penyewa;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRentalRequest;
use App\Models\Rental;
use App\Services\RentalBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalController extends Controller
{
    public function index(Request $request): View
    {
        $rentals = $request->user()->rentals()->with(['garage', 'vehicle'])
            ->orderByDesc('start_date')->orderByDesc('id')->paginate(15);

        return view('penyewa.rentals.index', ['rentals' => $rentals]);
    }

    public function create(Request $request): View
    {
        return view('penyewa.rentals.create', [
            'vehicles' => $request->user()->vehicles()->orderBy('brand')->get(),
            'vehicleTypes' => VehicleType::cases(),
            'garageTypes' => GarageType::cases(),
            // dipakai JS untuk menyaring pilihan tipe garasi
            'allowed' => collect(VehicleType::cases())->mapWithKeys(
                fn (VehicleType $t) => [$t->value => array_map(fn (GarageType $g) => $g->value, $t->allowedGarageTypes())],
            ),
            'garageLabels' => collect(GarageType::cases())->mapWithKeys(fn (GarageType $g) => [$g->value => $g->label()]),
        ]);
    }

    /** Pratinjau tanggal selesai, garasi tersedia, dan biaya (AJAX). */
    public function quote(Request $request, RentalBookingService $service): JsonResponse
    {
        return response()->json($service->quoteForJson($request->user(), $request->all()));
    }

    public function store(StoreRentalRequest $request, RentalBookingService $service): RedirectResponse
    {
        $rental = $service->book(
            $request->user(),
            $request->only(['vehicle_id', 'vehicle_type', 'battery_capacity_kwh', 'rental_type', 'days', 'start_date', 'garage_type', 'garage_id']),
            $request->vehicleData(),
            $request->file('payment_proof'),
        );

        return redirect()->route('penyewa.rentals.show', $rental)
            ->with('status', "Sewa garasi {$rental->garage->code} dibuat. Admin akan memverifikasi bukti pembayaran Anda.");
    }

    public function show(Request $request, Rental $rental): View
    {
        abort_unless($rental->user_id === $request->user()->id, 404);
        $rental->load(['garage', 'vehicle', 'extensions']);

        return view('penyewa.rentals.show', ['rental' => $rental]);
    }
}
