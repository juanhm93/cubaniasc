<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Review\ActiveEnrollmentNotFoundException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentLevelResolverTest extends TestCase
{
    use RefreshDatabase;

    private StudentLevelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(StudentLevelResolver::class);
    }

    public function test_resolves_level_from_most_recent_active_enrollment(): void
    {
        $student = Student::factory()->create();
        $olderLevel = Level::factory()->create(['name' => 'Older Level']);
        $recentLevel = Level::factory()->create(['name' => 'Recent Level']);

        $olderCourse = Course::factory()->create([
            'level_id' => $olderLevel->id,
            'is_active' => true,
        ]);
        $recentCourse = Course::factory()->create([
            'level_id' => $recentLevel->id,
            'is_active' => true,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $olderCourse->id,
            'enrolled_at' => now()->subMonth(),
            'status' => EnrollmentStatus::Active,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $recentCourse->id,
            'enrolled_at' => now()->subDay(),
            'status' => EnrollmentStatus::Active,
        ]);

        $level = $this->resolver->resolveLevel($student);

        $this->assertTrue($recentLevel->is($level));
    }

    public function test_ignores_inactive_courses(): void
    {
        $student = Student::factory()->create();
        $inactiveLevel = Level::factory()->create();
        $activeLevel = Level::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create([
                'level_id' => $inactiveLevel->id,
                'is_active' => false,
            ])->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Active,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create([
                'level_id' => $activeLevel->id,
                'is_active' => true,
            ])->id,
            'enrolled_at' => now()->subWeek(),
            'status' => EnrollmentStatus::Active,
        ]);

        $level = $this->resolver->resolveLevel($student);

        $this->assertTrue($activeLevel->is($level));
    }

    public function test_throws_when_student_has_no_active_enrollment(): void
    {
        $student = Student::factory()->create();

        $this->expectException(ActiveEnrollmentNotFoundException::class);

        $this->resolver->resolveLevel($student);
    }
}
