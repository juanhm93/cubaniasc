<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The hosting server's default engine created some tables as MyISAM, which
     * ignores foreign keys and transactions and caps index keys at 1000 bytes.
     */
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tableNames = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->where('ENGINE', '!=', 'InnoDB')
            ->pluck('TABLE_NAME');

        foreach ($tableNames as $tableName) {
            DB::statement("ALTER TABLE `{$tableName}` ENGINE = InnoDB");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
