<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Bukti bayar diverifikasi manual oleh admin (seperti verifikasi NIK).
            $table->string('payment_status', 20)->default('pending')->after('payment_proof_path')->index();
            $table->text('payment_rejection_reason')->nullable()->after('payment_status');
            $table->timestamp('payment_verified_at')->nullable()->after('payment_rejection_reason');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_verified_at')
                ->constrained('users')->nullOnDelete();
        });

        // Sewa yang sudah ada sebelum fitur verifikasi dianggap terverifikasi.
        DB::table('rentals')->update(['payment_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->dropColumn(['payment_status', 'payment_rejection_reason', 'payment_verified_at']);
        });
    }
};
