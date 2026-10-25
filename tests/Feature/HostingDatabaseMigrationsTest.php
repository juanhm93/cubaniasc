<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HostingDatabaseMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_connections_create_innodb_tables_by_default(): void
    {
        $this->assertSame('InnoDB', config('database.connections.mysql.engine'));
        $this->assertSame('InnoDB', config('database.connections.mariadb.engine'));
    }

    public function test_personal_access_tokens_migration_rebuilds_a_leftover_table(): void
    {
        Schema::drop('personal_access_tokens');
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
        });

        $this->migration('2026_07_11_041919_create_personal_access_tokens_table.php')->up();

        $this->assertTrue(Schema::hasColumns('personal_access_tokens', ['token', 'abilities', 'expires_at']));

        $indexedColumns = collect(Schema::getIndexes('personal_access_tokens'))->pluck('columns')->all();

        $this->assertContains(['tokenable_type', 'tokenable_id'], $indexedColumns);
    }

    public function test_innodb_conversion_migration_is_a_no_op_outside_mysql(): void
    {
        $this->migration('2026_10_25_015354_convert_mysql_tables_to_innodb.php')->up();

        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
    }

    private function migration(string $fileName): Migration
    {
        return require database_path("migrations/{$fileName}");
    }
}
