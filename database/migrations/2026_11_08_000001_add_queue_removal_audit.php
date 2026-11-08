<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_queue_entries', function (Blueprint $table): void {
            $table->foreignId('removed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->string('removal_reason', 30)->nullable();
            $table->string('removal_details', 500)->nullable();
            $table->index(['organization_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_queue_entries', function (Blueprint $table): void {
            $table->dropForeign(['removed_by']);
            $table->dropIndex(['organization_id', 'removed_at']);
            $table->dropColumn(['removed_by', 'removed_at', 'removal_reason', 'removal_details']);
        });
    }
};
