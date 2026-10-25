<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each step is guarded so a run that failed halfway on MySQL (DDL is not
     * transactional) can be retried from the maintenance page. Required moments
     * use dateTime because NOT NULL timestamps get invalid implicit defaults
     * when explicit_defaults_for_timestamp is off on the hosting server.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('levels', 'review_duration_seconds')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->unsignedSmallInteger('review_duration_seconds')->default(1800)->after('dance_type_id');
            });
        }

        if (! Schema::hasTable('recommended_songs')) {
            Schema::create('recommended_songs', function (Blueprint $table) {
                $table->id();
                $table->string('title', 160);
                $table->string('artist', 160);
                $table->string('audio_or_link_url', 512);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('level_recommended_song')) {
            Schema::create('level_recommended_song', function (Blueprint $table) {
                $table->foreignId('level_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recommended_song_id')->constrained()->cascadeOnDelete();

                $table->unique(['level_id', 'recommended_song_id']);
            });
        }

        if (! Schema::hasTable('review_sessions')) {
            Schema::create('review_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('level_id')->constrained()->restrictOnDelete();
                $table->dateTime('started_at');
                $table->dateTime('expires_at')->index();
                $table->boolean('completed')->default(false)->index();
                $table->timestamps();

                $table->index(['student_id', 'started_at']);
            });
        }

        if (! Schema::hasTable('review_session_figures')) {
            Schema::create('review_session_figures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('level_content_id')->constrained()->cascadeOnDelete();
                $table->boolean('selected_by_student')->default(false);

                $table->unique(['review_session_id', 'level_content_id'], 'rsf_session_lc_unique');
            });
        }

        if (! Schema::hasTable('review_session_songs')) {
            Schema::create('review_session_songs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recommended_song_id')->constrained()->restrictOnDelete();

                $table->unique(['review_session_id', 'recommended_song_id'], 'rss_session_song_unique');
            });
        }

        if (! Schema::hasTable('student_figure_views')) {
            Schema::create('student_figure_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('level_content_id')->constrained()->cascadeOnDelete();
                $table->dateTime('viewed_at');

                $table->index(['student_id', 'viewed_at']);
            });
        }

        if (! Schema::hasTable('student_streaks')) {
            Schema::create('student_streaks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('current_streak')->default(0);
                $table->timestamp('last_review_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quiz_items')) {
            Schema::create('quiz_items', function (Blueprint $table) {
                $table->id();
                $table->string('type', 32)->index();
                $table->string('prompt', 255);
                $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quiz_options')) {
            Schema::create('quiz_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_item_id')->constrained()->cascadeOnDelete();
                $table->string('description', 500);
                $table->boolean('is_correct')->default(false);
            });
        }

        if (! Schema::hasTable('review_quiz_responses')) {
            Schema::create('review_quiz_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quiz_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('quiz_option_id')->constrained()->restrictOnDelete();
                $table->boolean('is_correct');
                $table->dateTime('answered_at');

                $table->index(['review_session_id', 'answered_at']);
            });
        }
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
