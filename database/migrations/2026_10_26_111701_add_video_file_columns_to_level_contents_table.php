<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'video_file_path',
        'video_file_size_bytes',
        'video_file_duration_seconds',
        'video_file_version',
        'video_file_uploaded_at',
    ];

    /**
     * Run the migrations.
     *
     * Adds the figure's own MP4 (object key in the R2 bucket) next to the YouTube
     * `video_url`, which stays untouched. `video_file_version` goes up every time the
     * file is uploaded, replaced or removed, so the app can tell its copy is stale.
     *
     * Each column is guarded so a run that failed halfway on MySQL (DDL is not
     * transactional) can be retried from the maintenance page.
     */
    public function up(): void
    {
        Schema::table('level_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('level_contents', 'video_file_path')) {
                $table->string('video_file_path', 255)->nullable()->after('video_url');
            }

            if (! Schema::hasColumn('level_contents', 'video_file_size_bytes')) {
                $table->unsignedBigInteger('video_file_size_bytes')->nullable()->after('video_file_path');
            }

            if (! Schema::hasColumn('level_contents', 'video_file_duration_seconds')) {
                $table->unsignedInteger('video_file_duration_seconds')->nullable()->after('video_file_size_bytes');
            }

            if (! Schema::hasColumn('level_contents', 'video_file_version')) {
                $table->unsignedInteger('video_file_version')->default(0)->after('video_file_duration_seconds');
            }

            if (! Schema::hasColumn('level_contents', 'video_file_uploaded_at')) {
                $table->dateTime('video_file_uploaded_at')->nullable()->after('video_file_version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('level_contents', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('level_contents', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
