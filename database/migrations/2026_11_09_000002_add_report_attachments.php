<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_issue_requests', function (Blueprint $table): void {
            $table->json('attachments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('driver_issue_requests', fn (Blueprint $table) => $table->dropColumn('attachments'));
    }
};
