<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->string('trip_id', 40)->unique();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->unsignedInteger('passenger_count');
            $table->string('status')->default('in_progress');
            $table->timestamp('departure_time');
            $table->timestamp('arrival_time')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['driver_id', 'departure_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
