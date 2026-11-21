<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $types = collect(GarageType::cases())->map(fn (GarageType $t) => [
            'type' => $t,
            'total' => Garage::where('type', $t)->count(),
            'used' => Garage::where('type', $t)->rentedOn()->count(),
        ]);

        $active = Rental::query()->approved()->with(['penyewa', 'garage', 'vehicle'])
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today());

        return view('admin.dashboard', [
            'types' => $types,
            'pendingCount' => User::pendingVerification()->count(),
            'pendingPayments' => Rental::where('payment_status', 'pending')->count(),
            'activeCount' => (clone $active)->count(),
            'monthRevenue' => Rental::approved()->whereBetween('start_date', [today()->startOfMonth(), today()->endOfMonth()])->sum('total_price'),
            'endingSoon' => (clone $active)->whereDate('end_date', '<=', today()->addDays(3))->orderBy('end_date')->limit(6)->get(),
            'pending' => User::pendingVerification()->latest()->limit(5)->get(),
        ]);
    }
}
