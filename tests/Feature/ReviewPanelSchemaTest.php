<?php

namespace Tests\Feature;

use App\Enums\QuizItemType;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\RecommendedSong;
use App\Models\ReviewQuizResponse;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use App\Models\StudentStreak;
use Database\Seeders\LevelCatalogSeeder;
use Database\Seeders\ReviewPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPanelSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_levels_have_review_duration_seconds_column(): void
    {
        $level = Level::factory()->create();

        $this->assertDatabaseHas('levels', [
            'id' => $level->id,
            'review_duration_seconds' => 300,
        ]);
    }

    public function test_recommended_songs_can_belong_to_multiple_levels(): void
    {
        $levels = Level::factory()->count(2)->create();
        $song = RecommendedSong::factory()->create();

        $song->levels()->attach($levels->pluck('id'));

        $this->assertCount(2, $song->levels);
        $this->assertDatabaseCount('level_recommended_song', 2);
    }

    public function test_review_session_relationships_persist(): void
    {
        $student = Student::factory()->create();
        $level = Level::factory()->create(['review_duration_seconds' => 420]);
        $levelContent = LevelContent::factory()->for($level)->create();
        $song = RecommendedSong::factory()->create();

        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);

        $session->figures()->attach($levelContent->id, ['selected_by_student' => true]);
        $session->songs()->attach($song->id);

        $quizItem = QuizItem::factory()->figure()->for($level)->create();
        $correctOption = QuizOption::factory()->for($quizItem)->correct()->create();
        QuizOption::factory()->count(2)->for($quizItem)->create();

        ReviewQuizResponse::factory()->create([
            'review_session_id' => $session->id,
            'quiz_item_id' => $quizItem->id,
            'quiz_option_id' => $correctOption->id,
            'is_correct' => true,
            'answered_at' => now(),
        ]);

        $this->assertDatabaseHas('review_sessions', [
            'id' => $session->id,
            'student_id' => $student->id,
            'level_id' => $level->id,
            'completed' => false,
        ]);

        $this->assertDatabaseHas('review_session_figures', [
            'review_session_id' => $session->id,
            'level_content_id' => $levelContent->id,
            'selected_by_student' => true,
        ]);

        $this->assertDatabaseHas('review_session_songs', [
            'review_session_id' => $session->id,
            'recommended_song_id' => $song->id,
        ]);

        $this->assertDatabaseHas('review_quiz_responses', [
            'review_session_id' => $session->id,
            'quiz_item_id' => $quizItem->id,
            'is_correct' => true,
        ]);

        $session->load('figures', 'songs');

        $this->assertCount(1, $session->figures);
        $this->assertEquals(1, $session->figures->first()->pivot->selected_by_student);
        $this->assertCount(1, $session->songs);
        $this->assertTrue($song->is($session->songs->first()));
    }

    public function test_student_figure_views_and_streaks_persist(): void
    {
        $student = Student::factory()->create();
        $levelContent = LevelContent::factory()->create();

        StudentFigureView::factory()->create([
            'student_id' => $student->id,
            'level_content_id' => $levelContent->id,
            'viewed_at' => now()->subDay(),
        ]);

        StudentStreak::factory()->create([
            'student_id' => $student->id,
            'current_streak' => 3,
            'last_review_at' => now()->subDay(),
        ]);

        $this->assertDatabaseHas('student_figure_views', [
            'student_id' => $student->id,
            'level_content_id' => $levelContent->id,
        ]);

        $this->assertDatabaseHas('student_streaks', [
            'student_id' => $student->id,
            'current_streak' => 3,
        ]);
    }

    public function test_quiz_item_casts_type_enum(): void
    {
        $quizItem = QuizItem::factory()->funFact()->global()->create([
            'prompt' => '¿Quién popularizó la salsa casino?',
        ]);

        $this->assertSame(QuizItemType::FunFact, $quizItem->fresh()->type);
        $this->assertNull($quizItem->level_id);
    }

    public function test_review_panel_seeder_populates_catalog(): void
    {
        $this->seed(LevelCatalogSeeder::class);
        $this->seed(ReviewPanelSeeder::class);

        $this->assertDatabaseHas('recommended_songs', [
            'title' => 'La Vida Es Un Carnaval',
            'artist' => 'Celia Cruz',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('quiz_items', [
            'type' => QuizItemType::FunFact->value,
            'prompt' => '¿De dónde proviene la salsa casino?',
            'level_id' => null,
        ]);

        $funFactItem = QuizItem::query()
            ->where('prompt', '¿De dónde proviene la salsa casino?')
            ->firstOrFail();

        $this->assertSame(3, $funFactItem->options()->count());
        $this->assertSame(1, $funFactItem->options()->where('is_correct', true)->count());
    }
}
