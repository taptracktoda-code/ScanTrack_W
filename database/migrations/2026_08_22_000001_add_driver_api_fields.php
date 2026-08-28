<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfc_cards')) {
            Schema::table('nfc_cards', function (Blueprint $table) {
                if (! Schema::hasColumn('nfc_cards', 'uid')) $table->string('uid')->nullable()->unique();
                if (! Schema::hasColumn('nfc_cards', 'driver_id')) $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            });
        }

        if (Schema::hasTable('driver_logs')) {
            Schema::table('driver_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_logs', 'driver_id')) $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                if (! Schema::hasColumn('driver_logs', 'attendance_type')) $table->string('attendance_type')->nullable();
                if (! Schema::hasColumn('driver_logs', 'terminal_id')) $table->string('terminal_id')->nullable();
                if (! Schema::hasColumn('driver_logs', 'scanned_at')) $table->timestamp('scanned_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['nfc_cards' => ['uid', 'driver_id'], 'driver_logs' => ['driver_id', 'attendance_type', 'terminal_id', 'scanned_at']] as $table => $columns) {
            if (Schema::hasTable($table)) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)))));
        }
    }
};
