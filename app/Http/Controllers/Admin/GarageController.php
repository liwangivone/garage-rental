<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\GaragePrice;
use App\Services\GarageBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GarageController extends Controller
{
    public function index(Request $request): View
    {
        $type = GarageType::tryFrom((string) $request->query('type'));

        $garages = Garage::query()
            ->with(['currentRental.penyewa'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('type')->orderBy('sequence')
            ->paginate(20)->withQueryString();

        return view('admin.garages.index', [
            'garages' => $garages,
            'type' => $type,
            'prices' => GaragePrice::all()->keyBy(fn (GaragePrice $p) => $p->type->value),
        ]);
    }

    public function create(): View
    {
        return view('admin.garages.create', [
            'prices' => GaragePrice::all()->mapWithKeys(fn (GaragePrice $p) => [$p->type->value => [
                'daily' => (int) $p->daily_price,
                'kwh' => $p->electricity_price_per_kwh !== null ? (int) $p->electricity_price_per_kwh : null,
            ]]),
            'nextCodes' => collect(GarageType::cases())->mapWithKeys(
                fn (GarageType $t) => [$t->value => Garage::makeCode($t, min(Garage::nextSequence($t), Garage::MAX_PER_TYPE))],
            ),
        ]);
    }

    public function store(Request $request, GarageBatchService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(GarageType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Garage::MAX_PER_TYPE],
            'daily_price' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'electricity_price_per_kwh' => [
                Rule::requiredIf($request->input('type') === GarageType::ElectricCar->value),
                'nullable', 'numeric', 'min:1', 'max:9999999',
            ],
        ]);

        $type = GarageType::from($data['type']);
        $batch = $service->create(
            $request->user(), $type, (int) $data['quantity'],
            (string) $data['daily_price'], isset($data['electricity_price_per_kwh']) ? (string) $data['electricity_price_per_kwh'] : null,
        );

        $codes = $batch->garages()->orderBy('sequence')->pluck('code');

        return redirect()->route('admin.garages.index', ['type' => $type->value])
            ->with('status', "{$batch->quantity} {$type->label()} dibuat: {$codes->first()}".($codes->count() > 1 ? " – {$codes->last()}" : '').'.');
    }
}
