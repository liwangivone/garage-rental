<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Snapshot pengisian: sisa baterai saat sewa dibuat dan kWh yang harus diisi
            // (kapasitas maks - sisa). Null untuk sewa tanpa biaya listrik.
            $table->decimal('battery_current_kwh', 8, 2)->nullable()->after('electricity_price_per_kwh');
            $table->decimal('battery_charge_kwh', 8, 2)->nullable()->after('battery_current_kwh');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['battery_current_kwh', 'battery_charge_kwh']);
        });
    }
};
