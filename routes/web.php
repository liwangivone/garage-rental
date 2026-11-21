<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
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

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
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

    Route::get('/rentals', [Admin\RentalController::class, 'index'])->name('rentals.index');
    Route::get('/rentals/{rental}', [Admin\RentalController::class, 'show'])->name('rentals.show');
    Route::post('/rentals/{rental}/approve', [Admin\RentalController::class, 'approve'])->name('rentals.approve');
    Route::post('/rentals/{rental}/reject', [Admin\RentalController::class, 'reject'])->name('rentals.reject');
    Route::post('/rentals/{rental}/extend', [Admin\RentalController::class, 'extend'])->name('rentals.extend');
    Route::delete('/rentals/{rental}', [Admin\RentalController::class, 'destroy'])->name('rentals.destroy');

    Route::get('/reports', Admin\ReportController::class)->name('reports.index');
});

Route::prefix('penyewa')->name('penyewa.')->middleware(['auth', 'penyewa'])->group(function () {
    Route::get('/', Penyewa\DashboardController::class)->name('dashboard');

    Route::get('/profile', [Penyewa\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Penyewa\ProfileController::class, 'update'])->name('profile.update');

    Route::get('/rentals', [Penyewa\RentalController::class, 'index'])->name('rentals.index');
    Route::middleware('approved')->group(function () {
        Route::get('/rentals/create', [Penyewa\RentalController::class, 'create'])->name('rentals.create');
        Route::get('/rentals/quote', [Penyewa\RentalController::class, 'quote'])->name('rentals.quote');
        Route::post('/rentals', [Penyewa\RentalController::class, 'store'])->name('rentals.store');
    });
    Route::get('/rentals/{rental}', [Penyewa\RentalController::class, 'show'])->name('rentals.show');
});
