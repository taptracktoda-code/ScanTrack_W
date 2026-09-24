<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('qr_codes', 'generated_at')) {
            Schema::table('qr_codes', function (Blueprint $table): void {
                $table->timestamp('generated_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('qr_codes', 'generated_at')) {
            Schema::table('qr_codes', function (Blueprint $table): void {
                $table->dropColumn('generated_at');
            });
        }
    }
};