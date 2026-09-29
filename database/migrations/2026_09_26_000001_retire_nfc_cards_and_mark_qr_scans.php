<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfc_cards') && Schema::hasTable('qr_codes')) {
            DB::table('nfc_cards')->whereNotNull('driver_id')->orderBy('id')->get()->each(function (object $card): void {
                $driverExists = DB::table('drivers')->where('id', $card->driver_id)->exists();
                $qrExists = DB::table('qr_codes')->where('driver_id', $card->driver_id)->exists();

                if (! $driverExists || $qrExists) {
                    return;
                }

                do {
                    $token = bin2hex(random_bytes(16));
                } while (DB::table('qr_codes')->where('qr_token', $token)->exists());

                DB::table('qr_codes')->insert([
                    'driver_id' => $card->driver_id,
                    'qr_token' => $token,
                    'status' => 'Active',
                    'generated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            Schema::drop('nfc_cards');
        }

        if (Schema::hasTable('driver_logs') && ! Schema::hasColumn('driver_logs', 'scan_method')) {
            Schema::table('driver_logs', function (Blueprint $table): void {
                $table->string('scan_method', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_logs') && Schema::hasColumn('driver_logs', 'scan_method')) {
            Schema::table('driver_logs', function (Blueprint $table): void {
                $table->dropColumn('scan_method');
            });
        }

        if (! Schema::hasTable('nfc_cards')) {
            Schema::create('nfc_cards', function (Blueprint $table): void {
                $table->id();
                $table->string('uid')->nullable()->unique();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->timestamps();
            });
        }
    }
};