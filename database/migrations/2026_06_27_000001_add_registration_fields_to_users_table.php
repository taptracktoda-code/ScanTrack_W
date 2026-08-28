<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('user_id')->unique()->after('id');
            $table->string('license_number')->after('email');
            $table->string('contact')->after('license_number');
            $table->text('address')->after('contact');
            $table->string('status')->default('Active')->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'license_number', 'contact', 'address', 'status']);
        });
    }
};
