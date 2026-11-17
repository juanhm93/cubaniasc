<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Company;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Place;
use App\Models\RecommendedSong;
use App\Models\Student;
use App\Models\StudentLoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_attendance_is_only_recorded_for_students_of_the_course(): void
    {
        $admin = $this->createUserWithRole('admin');
        $course = Course::factory()->create(['company_id' => $admin->company_id, 'user_id' => $admin->id]);
        $session = CourseSession::factory()->create(['course_id' => $course->id]);
        $outsider = Student::factory()->create();
        $formerStudent = Student::factory()->create();
        Enrollment::factory()->create([
            'course_id' => $course->id,
            'student_id' => $formerStudent->id,
            'status' => EnrollmentStatus::Withdrawn,
        ]);

        $url = route('admin.courses.sessions.attendance.store', ['course' => $course->id, 'courseSession' => $session->id]);

        $this->actingAs($admin)
            ->post($url, ['student_id' => $outsider->id, 'status' => AttendanceStatus::Present->value])
            ->assertSessionHasErrors('student_id');

        $this->actingAs($admin)
            ->post($url, ['student_id' => $formerStudent->id, 'status' => AttendanceStatus::Absent->value])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('attendances', ['student_id' => $outsider->id]);
        $this->assertDatabaseHas('attendances', ['student_id' => $formerStudent->id]);
    }

    public function test_pending_or_role_less_users_cannot_teach_a_course(): void
    {
        $companyId = Company::factory()->create()->id;
        $admin = $this->createUserWithRole('admin', ['company_id' => $companyId]);
        $pending = User::factory()->create(['company_id' => $companyId, 'status' => 'pending']);
        $roleLess = User::factory()->create(['company_id' => $companyId, 'role_id' => null]);
        $teacher = $this->createUserWithRole('teacher', ['company_id' => $companyId]);

        $this->actingAs($admin)
            ->get(route('admin.courses.create'))
            ->assertInertia(fn (Assert $page) => $page->where('teachers', fn ($teachers): bool => collect($teachers)->pluck('id')->sort()->values()->all()
                === collect([$admin->id, $teacher->id])->sort()->values()->all()));

        foreach ([$pending, $roleLess] as $notATeacher) {
            $this->actingAs($admin)
                ->post(route('admin.courses.store'), $this->coursePayload($companyId, $notATeacher))
                ->assertSessionHasErrors('user_id');
        }

        $this->actingAs($admin)
            ->post(route('admin.courses.store'), $this->coursePayload($companyId, $teacher))
            ->assertSessionHasNoErrors();
    }

    public function test_a_course_keeps_its_current_teacher_even_if_no_longer_eligible(): void
    {
        $admin = $this->createUserWithRole('admin');
        $formerTeacher = User::factory()->create(['status' => 'pending', 'company_id' => $admin->company_id]);
        $course = Course::factory()->create(['company_id' => $admin->company_id, 'user_id' => $formerTeacher->id]);

        $this->actingAs($admin)
            ->patch(route('admin.courses.update', $course), [
                'is_active' => true,
                'user_id' => $formerTeacher->id,
                'place_id' => $course->place_id,
            ])
            ->assertSessionDoesntHaveErrors('user_id');
    }

    public function test_only_admins_and_the_owner_delete_songs(): void
    {
        $song = RecommendedSong::factory()->create();

        $this->actingAs($this->createUserWithRole('teacher'))
            ->delete(route('admin.recommended-songs.destroy', $song))
            ->assertForbidden();

        $this->assertNotSoftDeleted($song);
    }

    public function test_student_page_receives_only_the_fields_it_shows(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->create(['student_id' => $student->id]);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.students.show', $student))
            ->assertInertia(fn (Assert $page) => $page
                ->where('student.id', $student->id)
                ->has('student.enrollments.0.course.level')
                ->missing('student.created_at')
                ->missing('student.updated_at')
                ->missing('student.deleted_at'));
    }

    public function test_expired_login_codes_are_pruned(): void
    {
        $student = Student::factory()->create();
        $old = StudentLoginCode::factory()->for($student)->create(['expires_at' => now()->subDays(2)]);
        $recent = StudentLoginCode::factory()->for($student)->create(['expires_at' => now()->subMinutes(30)]);

        Artisan::call('model:prune', ['--model' => [StudentLoginCode::class]]);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_cors_only_allows_the_site_origin(): void
    {
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/v1/me', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $siteOrigin = rtrim(config('app.url'), '/');

        // With a single allowed origin the header always names the site, never the caller or `*`,
        // so the browser blocks any other origin.
        $this->assertSame($siteOrigin, $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
        $preflight($siteOrigin)->assertHeader('Access-Control-Allow-Origin', $siteOrigin);
    }

    /**
     * @return array<string, mixed>
     */
    private function coursePayload(int $companyId, User $teacher): array
    {
        return [
            'level_id' => Level::factory()->create()->id,
            'place_id' => Place::factory()->create(['company_id' => $companyId])->id,
            'user_id' => $teacher->id,
            'price' => 50,
            'is_active' => true,
            'slots' => [['weekday' => 1, 'starts_at' => '17:00', 'ends_at' => '18:00']],
        ];
    }
}
