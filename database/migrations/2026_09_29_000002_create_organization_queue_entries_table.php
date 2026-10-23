<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_queue_entries')) {
            Schema::create('organization_queue_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained('toda_organizations')->cascadeOnDelete();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->unsignedInteger('queue_number');
                $table->string('status')->default('WAITING');
                $table->foreignId('queued_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->foreignId('served_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('organization_queue_entries', ['organization_id', 'queue_number'], 'unique')) {
            Schema::table('organization_queue_entries', function (Blueprint $table): void {
                $table->unique(['organization_id', 'queue_number'], 'org_queue_org_number_unique');
            });
        }

        if (! Schema::hasIndex('organization_queue_entries', ['organization_id', 'status', 'queue_number'])) {
            Schema::table('organization_queue_entries', function (Blueprint $table): void {
                $table->index(['organization_id', 'status', 'queue_number'], 'org_queue_status_number_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_queue_entries');
    }
};
