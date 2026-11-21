<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat perpanjangan: admin mengubah tanggal selesai, sistem menghitung sisa biaya,
        // admin mengunggah bukti bayar.
        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete(); // admin
            $table->date('previous_end_date');
            $table->date('new_end_date');
            $table->string('previous_rental_type', 20);
            $table->string('new_rental_type', 20);
            $table->decimal('previous_total_price', 12, 2);
            $table->decimal('new_total_price', 12, 2);
            $table->decimal('additional_cost', 12, 2); // sisa biaya yang harus dibayar
            $table->string('payment_proof_path');
            $table->timestamps();

            $table->index('rental_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_extensions');
    }
};
