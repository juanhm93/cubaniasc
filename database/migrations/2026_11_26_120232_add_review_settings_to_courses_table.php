<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COURSE_COLUMNS = [
        'review_custom_settings',
        'review_previous_levels_count',
        'review_figure_options_count',
        'review_figure_selection_count',
        'review_duration_seconds',
    ];

    /**
     * Run the migrations.
     *
     * Per-course review settings. While `review_custom_settings` is false the course
     * reviews like before (every earlier level, 4 figures offered, 2 chosen, the level's
     * duration) and the other columns are ignored but kept. `review_previous_levels_count`
     * null means every earlier level; 0 only the current one.
     *
     * The session keeps the course it was started from so its settings stay the same
     * during the whole review.
     *
     * Each column is guarded so a run that failed halfway on MySQL (DDL is not
     * transactional) can be retried from the maintenance page.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'review_custom_settings')) {
                $table->boolean('review_custom_settings')->default(false);
            }

            if (! Schema::hasColumn('courses', 'review_previous_levels_count')) {
                $table->unsignedTinyInteger('review_previous_levels_count')->nullable();
            }

            if (! Schema::hasColumn('courses', 'review_figure_options_count')) {
                $table->unsignedTinyInteger('review_figure_options_count')->nullable();
            }

            if (! Schema::hasColumn('courses', 'review_figure_selection_count')) {
                $table->unsignedTinyInteger('review_figure_selection_count')->nullable();
            }

            if (! Schema::hasColumn('courses', 'review_duration_seconds')) {
                $table->unsignedSmallInteger('review_duration_seconds')->nullable();
            }
        });

        if (! Schema::hasColumn('review_sessions', 'course_id')) {
            Schema::table('review_sessions', function (Blueprint $table) {
                $table->foreignId('course_id')->nullable()->after('level_id')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('review_sessions', 'course_id')) {
            Schema::table('review_sessions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('course_id');
            });
        }

        $columns = array_values(array_filter(
            self::COURSE_COLUMNS,
            fn (string $column): bool => Schema::hasColumn('courses', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
