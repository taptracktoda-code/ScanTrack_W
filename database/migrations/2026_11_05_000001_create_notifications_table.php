<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isMysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        if ($isMysql) {
            // Legacy installations used MyISAM, which cannot be referenced by an InnoDB foreign key.
            $admins = DB::selectOne(
                'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [DB::connection()->getTablePrefix().'admins'],
            );
            if ($admins === null) {
                throw new RuntimeException('The admins table is missing. Apply the earlier account migrations first.');
            }
            if (strcasecmp($admins->engine, 'InnoDB') !== 0) {
                $tableName = DB::connection()->getQueryGrammar()->wrapTable('admins');
                DB::statement('ALTER TABLE '.$tableName.' ENGINE=InnoDB');
            }
        }
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
            Schema::create('expo_push_tokens', function (Blueprint $table) use ($isMysql): void {
                $table->engine('InnoDB');
                $table->id();
                $table->foreignId('user_id')->constrained('admins')->cascadeOnDelete();
                // API validation allows ASCII tokens only; keep the full index below MySQL's byte limit.
                $token = $table->string('token', 255)->unique();
                if ($isMysql) {
                    $token->charset('ascii')->collation('ascii_bin');
                }
                $table->timestamps();
            });
        }

        // MySQL can leave the table behind when CREATE succeeds but adding the index fails.
        if (! Schema::hasIndex('expo_push_tokens', ['token'], 'unique')) {
            if ($isMysql) {
                $tableName = DB::connection()->getQueryGrammar()->wrapTable('expo_push_tokens');
                DB::statement('ALTER TABLE '.$tableName.' ENGINE=InnoDB');
                Schema::table('expo_push_tokens', function (Blueprint $table): void {
                    $table->string('token', 255)->charset('ascii')->collation('ascii_bin')->change();
                });
            }

            Schema::table('expo_push_tokens', function (Blueprint $table): void {
                $table->unique('token');
            });
        }

        $hasUserForeignKey = collect(Schema::getForeignKeys('expo_push_tokens'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['user_id']);

        if (! $hasUserForeignKey) {
            Schema::table('expo_push_tokens', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('admins')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expo_push_tokens');
        Schema::dropIfExists('notifications');
    }
};
