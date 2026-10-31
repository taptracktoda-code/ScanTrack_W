<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing driver records; data cleanup must be reviewed and run explicitly.
    }

    public function down(): void
    {
    }
};
