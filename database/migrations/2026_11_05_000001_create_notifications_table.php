<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expo_push_tokens')) {
            Schema::create('expo_push_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('admins')->cascadeOnDelete();
                $table->string('token', 255)->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expo_push_tokens');
        Schema::dropIfExists('notifications');
    }
};
