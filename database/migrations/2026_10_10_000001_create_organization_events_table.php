<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('toda_organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location', 255);
            $table->timestamps();

            $table->index(['organization_id', 'event_date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_events');
    }
};
