<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garage_batch_id')->constrained('garage_batches')->restrictOnDelete();
            $table->string('type', 20); // bicycle | car | electric_car
            $table->unsignedSmallInteger('sequence'); // urutan per tipe, 1..999
            $table->string('code', 4)->unique(); // S001, C010, E003
            $table->string('status', 20)->default('available'); // available | used
            $table->timestamps();

            // Menjamin nomor urut tidak bentrok / tidak melebihi 3 digit per tipe.
            $table->unique(['type', 'sequence']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
