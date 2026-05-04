<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Company;
use App\Models\Course;
use App\Models\CourseScheduleSlot;
use App\Models\CourseSession;
use App\Models\DanceType;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Place;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourseAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_view_course_create_form(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.courses.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/courses/create')
                ->has('levels')
                ->has('places')
                ->has('teachers'));
    }

    public function test_admin_can_store_course_with_schedule_slots(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $companyId = Company::factory()->create()->id;
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            'company_id' => $companyId,
        ]);

        $level = Level::factory()->create();
        $place = Place::factory()->create(['company_id' => $companyId]);
        $teacher = User::factory()->create([
            'company_id' => $companyId,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.courses.store'), [
            'level_id' => $level->id,
            'place_id' => $place->id,
            'user_id' => $teacher->id,
            'price' => 99.5,
            'is_active' => false,
            'slots' => [
                ['weekday' => 1, 'starts_at' => '17:00', 'ends_at' => '18:00'],
                ['weekday' => 3, 'starts_at' => '17:00', 'ends_at' => '18:00'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();

        $course = Course::query()->latest('id')->first();

        $this->assertNotNull($course);
        $response->assertRedirect(route('admin.courses.show', $course));

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'level_id' => $level->id,
            'place_id' => $place->id,
            'user_id' => $teacher->id,
            'is_active' => false,
            'schedule_id' => null,
        ]);

        $this->assertDatabaseHas('course_schedule_slots', [
            'course_id' => $course->id,
            'weekday' => 1,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('course_schedule_slots', [
            'course_id' => $course->id,
            'weekday' => 3,
            'sort_order' => 1,
        ]);

        $this->assertSame(2, CourseScheduleSlot::query()->where('course_id', $course->id)->count());
    }

    public function test_admin_can_view_courses_index(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/courses/index')
                ->has('courses'));
    }

    public function test_admin_can_view_course_show(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $course = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
        ]);

        $session = CourseSession::factory()->create([
            'course_id' => $course->id,
        ]);

        $student = Student::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Active,
        ]);

        LevelContent::factory()->create([
            'level_id' => $course->level_id,
            'name' => 'Figura demo',
            'sort_order' => 1,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.courses.show', ['course' => $course->id]).'?session_id='.$session->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/courses/show')
                ->has('course')
                ->has('sessions')
                ->has('enrollments')
                ->has('courseProgress')
                ->has('levelContents')
                ->has('places')
                ->has('teachers')
                ->where('levelContents.0.video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_admin_can_toggle_course_active(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $course = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.courses.update', $course), [
            'is_active' => false,
            'user_id' => $course->user_id,
            'place_id' => $course->place_id,
        ])->assertRedirect(route('admin.courses.show', $course));

        $this->assertFalse($course->fresh()->is_active);
    }

    public function test_admin_can_reassign_course_teacher_and_place(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $otherTeacher = User::factory()->create([
            'company_id' => $admin->company_id,
        ]);

        $course = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'is_active' => true,
        ]);

        $newPlace = Place::factory()->create([
            'company_id' => $admin->company_id,
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.courses.update', $course), [
            'is_active' => true,
            'user_id' => $otherTeacher->id,
            'place_id' => $newPlace->id,
        ])->assertRedirect(route('admin.courses.show', $course));

        $course->refresh();

        $this->assertSame($otherTeacher->id, $course->user_id);
        $this->assertSame($newPlace->id, $course->place_id);
    }

    public function test_admin_can_record_attendance(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $course = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
        ]);

        $session = CourseSession::factory()->create([
            'course_id' => $course->id,
        ]);

        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->actingAs($admin);

        $this->post(
            route('admin.courses.sessions.attendance.store', [
                'course' => $course->id,
                'courseSession' => $session->id,
            ]),
            [
                'student_id' => $student->id,
                'status' => AttendanceStatus::Present->value,
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'course_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    public function test_admin_can_toggle_level_content_progress(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $course = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
        ]);

        $lc = LevelContent::factory()->create([
            'level_id' => $course->level_id,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin);

        $this->post(
            route('admin.courses.level-content-toggle', ['course' => $course->id]),
            ['level_content_id' => $lc->id]
        )->assertRedirect(route('admin.courses.show', $course));

        $this->assertDatabaseHas('course_level_content_progress', [
            'course_id' => $course->id,
            'level_content_id' => $lc->id,
        ]);

        $this->post(
            route('admin.courses.level-content-toggle', ['course' => $course->id]),
            ['level_content_id' => $lc->id]
        )->assertRedirect(route('admin.courses.show', $course));

        $this->assertDatabaseMissing('course_level_content_progress', [
            'course_id' => $course->id,
            'level_content_id' => $lc->id,
        ]);
    }

    public function test_admin_can_advance_course_level(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $danceType = DanceType::factory()->create();

        $levelA = Level::factory()->create([
            'dance_type_id' => $danceType->id,
            'sort_order' => 10,
        ]);

        $levelB = Level::factory()->create([
            'dance_type_id' => $danceType->id,
            'sort_order' => 20,
        ]);

        $course = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'level_id' => $levelA->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('admin.courses.advance-level', $course))
            ->assertRedirect(route('admin.courses.show', $course));

        $this->assertSame($levelB->id, $course->fresh()->level_id);
    }
}
