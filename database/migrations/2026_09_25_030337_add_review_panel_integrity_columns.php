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
        Schema::table('review_sessions', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('completed');
        });

        Schema::table('recommended_songs', function (Blueprint $table) {
            $table->softDeletes();
        });

        $this->removeDuplicatedQuizResponses();

        Schema::table('review_quiz_responses', function (Blueprint $table) {
            $table->unique(['review_session_id', 'quiz_item_id'], 'rqr_session_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_quiz_responses', function (Blueprint $table) {
            $table->dropUnique('rqr_session_item_unique');
        });

        Schema::table('recommended_songs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('review_sessions', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }

    /**
     * Keeps only the first answer per session and quiz item so the unique index can be created.
     */
    private function removeDuplicatedQuizResponses(): void
    {
        $firstResponseIds = DB::table('review_quiz_responses')
            ->selectRaw('MIN(id) as id')
            ->groupBy('review_session_id', 'quiz_item_id')
            ->pluck('id');

        DB::table('review_quiz_responses')
            ->whereNotIn('id', $firstResponseIds)
            ->delete();
    }
};
