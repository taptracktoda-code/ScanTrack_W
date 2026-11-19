<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', fn (Blueprint $table) => $table->string('assigned_terminal')->nullable());
    }

    public function down(): void
    {
        Schema::table('drivers', fn (Blueprint $table) => $table->dropColumn('assigned_terminal'));
    }
};
