<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu kali admin membuat garasi secara berkelompok.
        // Harga disimpan per kelompok; harga bulanan = 25 x harga harian (dihitung, tidak disimpan).
        Schema::create('garage_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // bicycle | car | electric_car
            $table->unsignedSmallInteger('quantity'); // 1..999
            $table->decimal('daily_price', 12, 2);
            $table->decimal('electricity_price_per_kwh', 12, 2)->nullable(); // hanya electric_car
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garage_batches');
    }
};
