<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\DanceType;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use App\Services\Review\FigureSelectionService;
use App\Services\Review\QuizGeneratorService;
use App\Services\Review\SongRecommendationService;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLevelsMixTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_levels_are_current_first_then_earlier_levels_of_the_same_dance_type(): void
    {
        [$student, $basic1, $basic2, $intermediate] = $this->createStudentInLevel(2);
        Level::factory()->create(['sort_order' => 1]);

        $levels = app(StudentLevelResolver::class)->resolveReviewLevels($student);

        $this->assertSame([$basic2->id, $basic1->id], $levels->pluck('id')->all());
        $this->assertFalse($levels->contains('id', $intermediate->id));
    }

    public function test_figure_options_keep_at_least_two_from_the_current_level(): void
    {
        [$student, $basic1, $basic2] = $this->createStudentInLevel(2);
        LevelContent::factory()->count(3)->for($basic2)->create();
        $previousFigures = LevelContent::factory()->count(4)->for($basic1)->create();

        foreach ($previousFigures as $index => $figure) {
            StudentFigureView::factory()->create([
                'student_id' => $student->id,
                'level_content_id' => $figure->id,
                'viewed_at' => now()->subMinutes($index),
            ]);
        }

        $options = app(FigureSelectionService::class)->getSelectableFigures($student, $basic2);

        $this->assertCount(4, $options);
        $this->assertSame(2, $options->where('level_id', $basic2->id)->count());
        $this->assertSame(2, $options->where('level_id', $basic1->id)->count());
    }

    public function test_figure_options_are_filled_with_earlier_levels_when_the_current_one_is_short(): void
    {
        [$student, $basic1, $basic2] = $this->createStudentInLevel(2);
        $currentFigure = LevelContent::factory()->for($basic2)->create();
        LevelContent::factory()->count(5)->for($basic1)->create();
        LevelContent::factory()->count(5)->create();

        $options = app(FigureSelectionService::class)->getSelectableFigures($student, $basic2);

        $this->assertCount(4, $options);
        $this->assertSame($currentFigure->id, $options->first()->id);
        $this->assertTrue($options->every(fn (LevelContent $figure): bool => in_array($figure->level_id, [$basic1->id, $basic2->id], true)));
    }

    public function test_selected_figures_can_come_from_an_earlier_level(): void
    {
        [$student, $basic1, $basic2] = $this->createStudentInLevel(2);
        LevelContent::factory()->for($basic2)->create();
        LevelContent::factory()->count(3)->for($basic1)->create();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $basic2->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
        $service = app(FigureSelectionService::class);

        $options = $service->optionsForSession($session, $student);
        $previousLevelIds = $options->where('level_id', $basic1->id)->pluck('id')->take(2)->values()->all();

        $service->storeSelectedFigures($session, $previousLevelIds);

        $this->assertEqualsCanonicalizing($previousLevelIds, $service->selectedFigures($session)->pluck('id')->all());
    }

    public function test_quiz_prioritizes_the_current_level_and_includes_earlier_levels(): void
    {
        [$student, $basic1, $basic2, $intermediate] = $this->createStudentInLevel(2);
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $basic2->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
        $currentItem = $this->createQuizItem($basic2);
        $previousItem = $this->createQuizItem($basic1);
        $this->createQuizItem($intermediate);
        $service = app(QuizGeneratorService::class);

        $this->assertSame($currentItem->id, $service->nextQuestion($session)->id);
        $this->assertTrue($service->belongsToSessionPool($session, $previousItem));
        $this->assertFalse($service->belongsToSessionPool($session, QuizItem::query()->where('level_id', $intermediate->id)->first()));
    }

    public function test_songs_are_filled_with_earlier_levels(): void
    {
        [, $basic1, $basic2] = $this->createStudentInLevel(2);
        RecommendedSong::factory()->create()->levels()->attach($basic2);
        RecommendedSong::factory()->count(3)->create()->each(fn (RecommendedSong $song) => $song->levels()->attach($basic1));

        $songs = app(SongRecommendationService::class)->recommendForLevel($basic2);

        $this->assertCount(3, $songs);
    }

    private function createQuizItem(Level $level): QuizItem
    {
        $item = QuizItem::factory()->create(['level_id' => $level->id]);
        QuizOption::factory()->correct()->create(['quiz_item_id' => $item->id]);
        QuizOption::factory()->count(2)->create(['quiz_item_id' => $item->id]);

        return $item;
    }

    /**
     * Creates Básico 1, Básico 2 and Intermedio of one dance type and enrolls a student
     * in the level with the given sort order.
     *
     * @return array{0: Student, 1: Level, 2: Level, 3: Level}
     */
    private function createStudentInLevel(int $sortOrder): array
    {
        $danceType = DanceType::factory()->create();
        $levels = collect([1, 2, 3])->map(fn (int $order): Level => Level::factory()->create([
            'dance_type_id' => $danceType->id,
            'sort_order' => $order,
        ]));

        $course = Course::factory()->create([
            'level_id' => $levels[$sortOrder - 1]->id,
            'is_active' => true,
        ]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, $levels[0], $levels[1], $levels[2]];
    }
}
