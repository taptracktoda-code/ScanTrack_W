<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_logs', function (Blueprint $table): void {
            $table->foreignId('entry_log_id')->nullable()->constrained('driver_logs')->nullOnDelete();
            $table->index(['driver_id', 'scanned_at']);
        });
        Schema::table('organization_queue_entries', function (Blueprint $table): void {
            $table->foreignId('entry_log_id')->nullable()->constrained('driver_logs')->nullOnDelete();
        });
        Schema::table('trips', function (Blueprint $table): void {
            $table->foreignId('queue_entry_id')->nullable()->unique()->constrained('organization_queue_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropForeign(['queue_entry_id']);
            $table->dropUnique(['queue_entry_id']);
            $table->dropColumn('queue_entry_id');
        });
        Schema::table('organization_queue_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('entry_log_id'));
        Schema::table('driver_logs', function (Blueprint $table): void {
            $table->dropIndex(['driver_id', 'scanned_at']);
            $table->dropConstrainedForeignId('entry_log_id');
        });
    }
};
