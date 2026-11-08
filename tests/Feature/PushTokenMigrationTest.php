<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PushTokenMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_migration_can_be_retried_without_losing_tokens(): void
    {
        Schema::drop('expo_push_tokens');
        Schema::create('expo_push_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 255);
            $table->timestamps();
        });
        $user = User::factory()->create();
        $token = 'ExpoPushToken[existing-token]';
        DB::table('expo_push_tokens')->insert(['user_id' => $user->id, 'token' => $token]);

        $migration = require database_path('migrations/2026_11_05_000001_create_notifications_table.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('expo_push_tokens', ['user_id' => $user->id, 'token' => $token]);
        $this->assertTrue(Schema::hasIndex('expo_push_tokens', ['token'], 'unique'));
        $this->assertNotEmpty(Schema::getForeignKeys('expo_push_tokens'));
        $user->delete();
        $this->assertDatabaseMissing('expo_push_tokens', ['token' => $token]);
    }

    public function test_mysql_token_column_uses_a_compact_case_sensitive_index(): void
    {
        $connection = new MySqlConnection(null, 'testing', '', [
            'driver' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $connection->setSchemaGrammar(new MySqlGrammar($connection));
        $originalDatabase = DB::getFacadeRoot();
        $originalSchema = Schema::getFacadeRoot();
        DB::shouldReceive('connection')->andReturn($connection);
        DB::shouldReceive('selectOne')->once()->with(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['admins'],
        )->andReturn((object) ['engine' => 'MyISAM']);
        DB::shouldReceive('statement')->once()->with('ALTER TABLE `admins` ENGINE=InnoDB')->andReturn(true);
        Schema::shouldReceive('hasTable')->with('notifications')->once()->andReturn(true);
        Schema::shouldReceive('hasTable')->with('expo_push_tokens')->once()->andReturn(false);
        Schema::shouldReceive('create')->with('expo_push_tokens', \Mockery::on(function ($callback) use ($connection): bool {
            $blueprint = new Blueprint($connection, 'expo_push_tokens');
            $blueprint->create();
            $callback($blueprint);
            $sql = implode("\n", $blueprint->toSql());

            $this->assertStringContainsString('`token` varchar(255) character set ascii collate \'ascii_bin\'', $sql);
            $this->assertStringContainsString('engine = InnoDB', $sql);
            $this->assertStringContainsString('unique `expo_push_tokens_token_unique`(`token`)', $sql);

            return true;
        }))->once();
        Schema::shouldReceive('hasIndex')->with('expo_push_tokens', ['token'], 'unique')->once()->andReturn(true);
        Schema::shouldReceive('getForeignKeys')->with('expo_push_tokens')->once()->andReturn([['columns' => ['user_id']]]);

        $migration = require database_path('migrations/2026_11_05_000001_create_notifications_table.php');
        try {
            $migration->up();
        } finally {
            DB::swap($originalDatabase);
            Schema::swap($originalSchema);
        }
    }
}
