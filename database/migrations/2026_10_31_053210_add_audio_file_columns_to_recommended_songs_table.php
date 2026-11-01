<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'audio_file_path',
        'audio_file_size_bytes',
        'audio_file_duration_seconds',
        'audio_file_version',
    ];

    /**
     * Run the migrations.
     *
     * Songs now point to a file of the music folder in the R2 bucket. The external
     * link `audio_or_link_url` becomes optional and unused, and is kept until
     * production has no songs left that depend on it. `audio_file_version` goes up
     * every time the file changes, so the app can tell its copy is stale.
     *
     * Each column is guarded so a run that failed halfway on MySQL (DDL is not
     * transactional) can be retried from the maintenance page.
     */
    public function up(): void
    {
        Schema::table('recommended_songs', function (Blueprint $table) {
            if (! Schema::hasColumn('recommended_songs', 'audio_file_path')) {
                $table->string('audio_file_path', 255)->nullable()->after('audio_or_link_url');
            }

            if (! Schema::hasColumn('recommended_songs', 'audio_file_size_bytes')) {
                $table->unsignedBigInteger('audio_file_size_bytes')->nullable()->after('audio_file_path');
            }

            if (! Schema::hasColumn('recommended_songs', 'audio_file_duration_seconds')) {
                $table->unsignedInteger('audio_file_duration_seconds')->nullable()->after('audio_file_size_bytes');
            }

            if (! Schema::hasColumn('recommended_songs', 'audio_file_version')) {
                $table->unsignedInteger('audio_file_version')->default(0)->after('audio_file_duration_seconds');
            }
        });

        Schema::table('recommended_songs', function (Blueprint $table) {
            $table->string('audio_or_link_url', 512)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('recommended_songs')->whereNull('audio_or_link_url')->update(['audio_or_link_url' => '']);

        Schema::table('recommended_songs', function (Blueprint $table) {
            $table->string('audio_or_link_url', 512)->nullable(false)->change();
        });

        $columns = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('recommended_songs', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('recommended_songs', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
