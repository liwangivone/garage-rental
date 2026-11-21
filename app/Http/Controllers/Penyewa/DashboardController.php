<?php

namespace App\Http\Controllers\Penyewa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $rentals = $user->rentals()->with(['garage', 'vehicle']);

        return view('penyewa.dashboard', [
            'user' => $user,
            'active' => (clone $rentals)->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->orderBy('end_date')->get(),
            'upcoming' => (clone $rentals)->whereDate('start_date', '>', today())->orderBy('start_date')->get(),
            'totalRentals' => $user->rentals()->count(),
            'vehicleCount' => $user->vehicles()->count(),
        ]);
    }
}
