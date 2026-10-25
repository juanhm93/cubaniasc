<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            $this->repairLeftoverTable();

            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }

    /**
     * A run that failed halfway on MySQL (DDL is not transactional) leaves the
     * table as MyISAM without its morph index. It is repaired in place because
     * the database channel may already have stored notifications in it.
     */
    private function repairLeftoverTable(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `notifications` ENGINE = InnoDB');
        }

        $hasMorphIndex = collect(Schema::getIndexes('notifications'))
            ->contains(fn (array $index): bool => $index['columns'] === ['notifiable_type', 'notifiable_id']);

        if (! $hasMorphIndex) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        }
    }
};
