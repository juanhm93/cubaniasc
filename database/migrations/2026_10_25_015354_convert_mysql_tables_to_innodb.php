<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys of the review panel tables, which MyISAM silently ignored.
     *
     * @var list<array{table: string, column: string, references: string, onDelete: string}>
     */
    private const REVIEW_PANEL_FOREIGN_KEYS = [
        ['table' => 'level_recommended_song', 'column' => 'level_id', 'references' => 'levels', 'onDelete' => 'cascade'],
        ['table' => 'level_recommended_song', 'column' => 'recommended_song_id', 'references' => 'recommended_songs', 'onDelete' => 'cascade'],
        ['table' => 'review_sessions', 'column' => 'student_id', 'references' => 'students', 'onDelete' => 'cascade'],
        ['table' => 'review_sessions', 'column' => 'level_id', 'references' => 'levels', 'onDelete' => 'restrict'],
        ['table' => 'review_session_figures', 'column' => 'review_session_id', 'references' => 'review_sessions', 'onDelete' => 'cascade'],
        ['table' => 'review_session_figures', 'column' => 'level_content_id', 'references' => 'level_contents', 'onDelete' => 'cascade'],
        ['table' => 'review_session_songs', 'column' => 'review_session_id', 'references' => 'review_sessions', 'onDelete' => 'cascade'],
        ['table' => 'review_session_songs', 'column' => 'recommended_song_id', 'references' => 'recommended_songs', 'onDelete' => 'restrict'],
        ['table' => 'student_figure_views', 'column' => 'student_id', 'references' => 'students', 'onDelete' => 'cascade'],
        ['table' => 'student_figure_views', 'column' => 'level_content_id', 'references' => 'level_contents', 'onDelete' => 'cascade'],
        ['table' => 'student_streaks', 'column' => 'student_id', 'references' => 'students', 'onDelete' => 'cascade'],
        ['table' => 'quiz_items', 'column' => 'level_id', 'references' => 'levels', 'onDelete' => 'set null'],
        ['table' => 'quiz_options', 'column' => 'quiz_item_id', 'references' => 'quiz_items', 'onDelete' => 'cascade'],
        ['table' => 'review_quiz_responses', 'column' => 'review_session_id', 'references' => 'review_sessions', 'onDelete' => 'cascade'],
        ['table' => 'review_quiz_responses', 'column' => 'quiz_item_id', 'references' => 'quiz_items', 'onDelete' => 'restrict'],
        ['table' => 'review_quiz_responses', 'column' => 'quiz_option_id', 'references' => 'quiz_options', 'onDelete' => 'restrict'],
    ];

    /**
     * Run the migrations.
     *
     * The hosting server's default engine created some tables as MyISAM, which
     * ignores foreign keys and transactions and caps index keys at 1000 bytes.
     * Both steps skip what is already in place, so the migration can be retried.
     */
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->convertTablesToInnoDb();
        $this->addMissingReviewPanelForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    private function convertTablesToInnoDb(): void
    {
        $tableNames = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->where('ENGINE', '!=', 'InnoDB')
            ->pluck('TABLE_NAME');

        foreach ($tableNames as $tableName) {
            DB::statement("ALTER TABLE `{$tableName}` ENGINE = InnoDB");
        }
    }

    private function addMissingReviewPanelForeignKeys(): void
    {
        foreach (self::REVIEW_PANEL_FOREIGN_KEYS as $foreignKey) {
            $alreadyExists = collect(Schema::getForeignKeys($foreignKey['table']))
                ->contains(fn (array $existing): bool => $existing['columns'] === [$foreignKey['column']]);

            if ($alreadyExists) {
                continue;
            }

            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey) {
                $table->foreign($foreignKey['column'])
                    ->references('id')
                    ->on($foreignKey['references'])
                    ->onDelete($foreignKey['onDelete']);
            });
        }
    }
};
