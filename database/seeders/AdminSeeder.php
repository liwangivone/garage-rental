<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Kredensial bisa diubah lewat .env; default hanya untuk development.
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@garagerental.test')],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'phone' => env('ADMIN_PHONE'),
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin,
                'registration_status' => RegistrationStatus::Approved,
                'email_verified_at' => now(),
                'verified_at' => now(),
            ],
        );
    }
}
