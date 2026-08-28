<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('license_number')->nullable()->after('name');
            $table->string('contact_number')->nullable()->after('license_number');
            $table->string('vehicle_number')->nullable()->after('contact_number');
            $table->string('status')->default('Active')->after('vehicle_number');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'license_number',
                'contact_number',
                'vehicle_number',
                'status',
            ]);
        });
    }
};
