<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_issue_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('queue_entry_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('driver_issue_requests')->whereNull('queue_entry_id')->exists()) {
            throw new RuntimeException('General reports exist. Preserve or archive them before reversing this migration.');
        }
        Schema::table('driver_issue_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('queue_entry_id')->nullable(false)->change();
        });
    }
};
