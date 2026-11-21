<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Penyewa;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    return redirect()->route(match (true) {
        $user === null => 'login',
        $user->isAdmin() => 'admin.dashboard',
        default => 'penyewa.dashboard',
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/garages', [Admin\GarageController::class, 'index'])->name('garages.index');
    Route::get('/garages/create', [Admin\GarageController::class, 'create'])->name('garages.create');
    Route::post('/garages', [Admin\GarageController::class, 'store'])->name('garages.store');

    Route::get('/registrations', [Admin\RegistrationController::class, 'index'])->name('registrations.index');
    Route::post('/registrations/{user}/approve', [Admin\RegistrationController::class, 'approve'])->name('registrations.approve');
    Route::post('/registrations/{user}/reject', [Admin\RegistrationController::class, 'reject'])->name('registrations.reject');

    Route::get('/extensions', [Admin\ExtensionController::class, 'index'])->name('extensions.index');
    Route::get('/extensions/{rental}', [Admin\ExtensionController::class, 'show'])->name('extensions.show');
    Route::post('/extensions/{rental}', [Admin\ExtensionController::class, 'update'])->name('extensions.update');

    Route::get('/reports', Admin\ReportController::class)->name('reports.index');
});

Route::prefix('penyewa')->name('penyewa.')->middleware(['auth', 'penyewa'])->group(function () {
    Route::get('/', Penyewa\DashboardController::class)->name('dashboard');

    Route::get('/rentals', [Penyewa\RentalController::class, 'index'])->name('rentals.index');
    Route::middleware('approved')->group(function () {
        Route::get('/rentals/create', [Penyewa\RentalController::class, 'create'])->name('rentals.create');
        Route::get('/rentals/quote', [Penyewa\RentalController::class, 'quote'])->name('rentals.quote');
        Route::post('/rentals', [Penyewa\RentalController::class, 'store'])->name('rentals.store');
    });
    Route::get('/rentals/{rental}', [Penyewa\RentalController::class, 'show'])->name('rentals.show');
});
