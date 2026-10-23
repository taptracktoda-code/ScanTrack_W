<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toda_organizations', function (Blueprint $table): void {
            $table->string('address')->nullable();
            $table->string('contact_number', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('Active');
        });

        Schema::table('admins', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('admins')->nullOnDelete();
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['registered_by']);
            $table->dropColumn(['approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason', 'registered_by']);
        });

        Schema::table('admins', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'first_name', 'middle_name', 'last_name']);
        });

        Schema::table('toda_organizations', function (Blueprint $table): void {
            $table->dropColumn(['address', 'contact_number', 'email', 'status']);
        });
    }
};