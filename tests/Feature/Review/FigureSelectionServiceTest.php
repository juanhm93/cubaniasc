<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Review\InvalidFigureSelectionException;
use App\Models\Course;
use App\Models\CourseLevelContentProgress;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use App\Services\Review\FigureSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FigureSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FigureSelectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FigureSelectionService::class);
    }

    public function test_returns_recent_figure_views_first(): void
    {
        [$student, $level, $course] = $this->createEnrolledStudentContext();
        $figures = LevelContent::factory()->count(5)->for($level)->create();

        StudentFigureView::factory()->create([
            'student_id' => $student->id,
            'level_content_id' => $figures[0]->id,
            'viewed_at' => now()->subDays(3),
        ]);
        StudentFigureView::factory()->create([
            'student_id' => $student->id,
            'level_content_id' => $figures[1]->id,
            'viewed_at' => now()->subDay(),
        ]);
        StudentFigureView::factory()->create([
            'student_id' => $student->id,
            'level_content_id' => $figures[2]->id,
            'viewed_at' => now(),
        ]);

        $selectable = $this->service->getSelectableFigures($student, $level);

        $this->assertCount(4, $selectable);
        $this->assertSame(
            [$figures[2]->id, $figures[1]->id, $figures[0]->id],
            $selectable->take(3)->pluck('id')->all(),
        );
    }

    public function test_fills_missing_slots_with_course_progress(): void
    {
        [$student, $level, $course] = $this->createEnrolledStudentContext();
        $viewedFigure = LevelContent::factory()->for($level)->create(['name' => 'Viewed']);
        $progressFigure = LevelContent::factory()->for($level)->create(['name' => 'Progress']);

        StudentFigureView::factory()->create([
            'student_id' => $student->id,
            'level_content_id' => $viewedFigure->id,
            'viewed_at' => now(),
        ]);

        CourseLevelContentProgress::query()->create([
            'course_id' => $course->id,
            'level_content_id' => $progressFigure->id,
            'completed_at' => now()->subDay(),
        ]);

        $selectable = $this->service->getSelectableFigures($student, $level, 2);

        $this->assertCount(2, $selectable);
        $this->assertTrue($selectable->contains(fn ($figure) => $figure->is($viewedFigure)));
        $this->assertTrue($selectable->contains(fn ($figure) => $figure->is($progressFigure)));
    }

    public function test_fills_remaining_slots_with_random_level_figures(): void
    {
        [$student, $level] = $this->createEnrolledStudentContext();
        $figures = LevelContent::factory()->count(4)->for($level)->create();

        $selectable = $this->service->getSelectableFigures($student, $level);

        $this->assertCount(4, $selectable);
        $this->assertTrue($selectable->every(fn ($figure) => $figure->level_id === $level->id));
        $this->assertSame(4, $selectable->pluck('id')->unique()->count());
        $this->assertTrue($figures->pluck('id')->intersect($selectable->pluck('id'))->isNotEmpty());
    }

    public function test_stores_exactly_two_selected_figures(): void
    {
        [$student, $level] = $this->createEnrolledStudentContext();
        $figures = LevelContent::factory()->count(2)->for($level)->create();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);

        $this->service->storeSelectedFigures(
            $session,
            $figures->pluck('id')->all(),
            $figures->pluck('id')->all(),
        );

        $this->assertDatabaseCount('review_session_figures', 2);
        $this->assertDatabaseHas('review_session_figures', [
            'review_session_id' => $session->id,
            'level_content_id' => $figures[0]->id,
            'selected_by_student' => true,
        ]);
    }

    public function test_rejects_invalid_figure_selection_count(): void
    {
        [$student, $level] = $this->createEnrolledStudentContext();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);

        $this->expectException(InvalidFigureSelectionException::class);

        $this->service->storeSelectedFigures($session, [1]);
    }

    public function test_records_figure_view(): void
    {
        [$student, $level] = $this->createEnrolledStudentContext();
        $figure = LevelContent::factory()->for($level)->create();

        $view = $this->service->recordView($student, $figure);

        $this->assertDatabaseHas('student_figure_views', [
            'id' => $view->id,
            'student_id' => $student->id,
            'level_content_id' => $figure->id,
        ]);
    }

    /**
     * @return array{0: Student, 1: Level, 2: Course}
     */
    private function createEnrolledStudentContext(): array
    {
        $level = Level::factory()->create();
        $course = Course::factory()->create([
            'level_id' => $level->id,
            'is_active' => true,
        ]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, $level, $course];
    }
}
