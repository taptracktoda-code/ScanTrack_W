<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('drivers', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('drivers', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('drivers', 'last_name')) {
                $table->string('last_name')->nullable()->after('middle_name');
            }
            if (! Schema::hasColumn('drivers', 'address')) {
                $table->text('address')->nullable()->after('contact_number');
            }
            if (! Schema::hasColumn('drivers', 'license_expiration_date')) {
                $table->date('license_expiration_date')->nullable()->after('license_number');
            }
            if (! Schema::hasColumn('drivers', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('vehicle_number')->constrained('toda_organizations')->nullOnDelete();
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'vehicle_type')) {
                $table->string('vehicle_type')->nullable()->after('plate_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'vehicle_type')) {
                $table->dropColumn('vehicle_type');
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            if (Schema::hasColumn('drivers', 'organization_id')) {
                $table->dropForeign(['organization_id']);
            }
            foreach (['first_name', 'middle_name', 'last_name', 'address', 'license_expiration_date', 'organization_id'] as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });
    }
};