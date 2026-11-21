<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nomor plat tidak lagi unik (mis. kendaraan berpindah pemilik).
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['plate_number']);
            $table->index('plate_number');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['plate_number']);
            $table->unique('plate_number');
        });
    }
};
