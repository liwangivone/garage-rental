<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_extensions', function (Blueprint $table) {
            // Biaya isi listrik tambahan, diisi admin saat perpanjangan (sudah termasuk di additional_cost).
            $table->decimal('electricity_cost', 12, 2)->default(0)->after('additional_cost');
        });
    }

    public function down(): void
    {
        Schema::table('rental_extensions', function (Blueprint $table) {
            $table->dropColumn('electricity_cost');
        });
    }
};
