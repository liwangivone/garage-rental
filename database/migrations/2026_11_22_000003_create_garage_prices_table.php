<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu harga per tipe garasi. Harga di garage_batches tinggal menjadi catatan historis.
        Schema::create('garage_prices', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->unique(); // bicycle | car | electric_car
            $table->decimal('daily_price', 12, 2);
            $table->decimal('electricity_price_per_kwh', 12, 2)->nullable(); // hanya electric_car
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Isi dari kelompok garasi terbaru per tipe (bila sudah ada data).
        foreach (['bicycle', 'car', 'electric_car'] as $type) {
            $latest = DB::table('garage_batches')->where('type', $type)->orderByDesc('id')->first();
            if ($latest) {
                DB::table('garage_prices')->insert([
                    'type' => $type,
                    'daily_price' => $latest->daily_price,
                    'electricity_price_per_kwh' => $latest->electricity_price_per_kwh,
                    'updated_by' => $latest->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('garage_prices');
    }
};
