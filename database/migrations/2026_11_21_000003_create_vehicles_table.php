<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // bicycle | motorcycle | car | electric_car
            $table->string('plate_number', 15)->nullable()->unique(); // null untuk sepeda
            $table->string('brand'); // merek
            $table->string('model'); // tipe
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable(); // hanya mobil listrik
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
