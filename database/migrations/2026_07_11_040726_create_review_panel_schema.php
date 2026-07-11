<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->unsignedSmallInteger('review_duration_seconds')->default(300)->after('dance_type_id');
        });

        Schema::create('recommended_songs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('artist', 160);
            $table->string('audio_or_link_url', 512);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('level_recommended_song', function (Blueprint $table) {
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommended_song_id')->constrained()->cascadeOnDelete();

            $table->unique(['level_id', 'recommended_song_id']);
        });

        Schema::create('review_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->index();
            $table->boolean('completed')->default(false)->index();
            $table->timestamps();

            $table->index(['student_id', 'started_at']);
        });

        Schema::create('review_session_figures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_content_id')->constrained()->cascadeOnDelete();
            $table->boolean('selected_by_student')->default(false);

            $table->unique(['review_session_id', 'level_content_id'], 'rsf_session_lc_unique');
        });

        Schema::create('review_session_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommended_song_id')->constrained()->restrictOnDelete();

            $table->unique(['review_session_id', 'recommended_song_id'], 'rss_session_song_unique');
        });

        Schema::create('student_figure_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_content_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->index(['student_id', 'viewed_at']);
        });

        Schema::create('student_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->timestamp('last_review_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('prompt', 255);
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_item_id')->constrained()->cascadeOnDelete();
            $table->string('description', 500);
            $table->boolean('is_correct')->default(false);
        });

        Schema::create('review_quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('quiz_option_id')->constrained()->restrictOnDelete();
            $table->boolean('is_correct');
            $table->timestamp('answered_at');

            $table->index(['review_session_id', 'answered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_quiz_responses');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_items');
        Schema::dropIfExists('student_streaks');
        Schema::dropIfExists('student_figure_views');
        Schema::dropIfExists('review_session_songs');
        Schema::dropIfExists('review_session_figures');
        Schema::dropIfExists('review_sessions');
        Schema::dropIfExists('level_recommended_song');
        Schema::dropIfExists('recommended_songs');

        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn('review_duration_seconds');
        });
    }
};
