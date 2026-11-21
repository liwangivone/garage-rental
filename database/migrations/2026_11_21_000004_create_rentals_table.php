<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu garasi yang disewa. Penyewa yang menyewa >1 garasi
        // membuat beberapa baris (masing-masing dengan kendaraan sendiri).
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // penyewa
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('garage_id')->constrained()->restrictOnDelete();

            // daily | monthly: monthly bila ada minimal satu blok bulanan (ditentukan sistem)
            $table->string('rental_type', 20);
            $table->date('start_date'); // dipilih penyewa, tidak boleh sebelum hari ini
            $table->date('end_date');
            $table->unsignedSmallInteger('duration_days'); // inklusif start & end
            // Rincian penagihan: sewa baru max 1 blok bulanan; perpanjangan bisa lebih dari 1.
            $table->unsignedSmallInteger('monthly_count')->default(0);
            $table->unsignedSmallInteger('daily_count')->default(0);

            // Snapshot harga saat transaksi, supaya laporan tidak berubah bila admin mengubah harga.
            $table->decimal('daily_price', 12, 2);
            $table->decimal('monthly_price', 12, 2); // 25 x daily_price
            $table->decimal('electricity_price_per_kwh', 12, 2)->nullable();
            $table->decimal('electricity_cost', 12, 2)->default(0); // kapasitas kWh x harga/kWh (mobil listrik di garasi listrik)
            $table->decimal('rental_cost', 12, 2); // biaya sewa saja
            $table->decimal('total_price', 12, 2); // rental_cost + electricity_cost; ikut diperbarui saat diperpanjang

            $table->string('payment_proof_path'); // bukti pelunasan (gambar)
            $table->timestamps();

            $table->index(['garage_id', 'start_date', 'end_date']); // cek ketersediaan
            $table->index('start_date'); // laporan per bulan / per hari
            $table->index(['user_id', 'start_date']); // laporan per penyewa
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
