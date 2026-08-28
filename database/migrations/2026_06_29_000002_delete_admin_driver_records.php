<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('drivers')) {
            return;
        }

        DB::table('drivers')->where('name', 'Administrator')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: the cleanup is destructive and should not be reversed automatically.
    }
};
