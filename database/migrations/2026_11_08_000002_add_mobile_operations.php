<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_queue_entries', function (Blueprint $table): void {
            $table->timestamp('next_notified_at')->nullable();
        });
        foreach (['trips', 'driver_logs'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
                $table->index(['organization_id', 'created_at']);
            });
            // Legacy records have no organization snapshot; use their driver's known assignment.
            DB::table($name)->orderBy('id')->chunkById(500, function ($rows) use ($name): void {
                $organizations = DB::table('drivers')->whereIn('id', $rows->pluck('driver_id'))->pluck('organization_id', 'id');
                foreach ($rows as $row) {
                    DB::table($name)->where('id', $row->id)->update(['organization_id' => $organizations[$row->driver_id] ?? null]);
                }
            });
        }
        Schema::create('driver_issue_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('toda_organizations')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->constrained('organization_queue_entries')->cascadeOnDelete();
            $table->foreignId('pending_entry_id')->nullable()->unique()->constrained('organization_queue_entries')->cascadeOnDelete();
            $table->string('client_request_id', 80)->unique();
            $table->string('type', 30);
            $table->text('explanation');
            $table->string('status', 20)->default('pending');
            $table->text('decision_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_issue_requests');
        foreach (['trips', 'driver_logs'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['organization_id']);
                $table->dropIndex(['organization_id', 'created_at']);
                $table->dropColumn('organization_id');
            });
        }
        Schema::table('organization_queue_entries', fn (Blueprint $table) => $table->dropColumn('next_notified_at'));
    }
};
