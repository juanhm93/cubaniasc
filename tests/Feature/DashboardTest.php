<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_includes_stats_and_staff_example(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stats.activeStudents', 0)
                ->where('stats.activeCourses', 0)
                ->where('staffExample.professors', 3)
                ->where('staffExample.administrativeStaff', 1)
                ->where('staffExample.administrators', 2)
                ->where('canManageCourses', false));
    }

    public function test_dashboard_counts_active_students_and_active_courses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $inactiveCourse = Course::factory()->create(['is_active' => false]);
        $activeCourse = Course::factory()->create(['is_active' => true]);

        $studentA = Student::factory()->create();
        $studentB = Student::factory()->create();

        Enrollment::factory()->create([
            'course_id' => $inactiveCourse->id,
            'student_id' => $studentA->id,
            'status' => EnrollmentStatus::Active,
        ]);

        Enrollment::factory()->create([
            'course_id' => $activeCourse->id,
            'student_id' => $studentA->id,
            'status' => EnrollmentStatus::Active,
        ]);

        Enrollment::factory()->create([
            'course_id' => $activeCourse->id,
            'student_id' => $studentB->id,
            'status' => EnrollmentStatus::Active,
        ]);

        Course::factory()->create(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.activeStudents', 2)
                ->where('stats.activeCourses', 1));
    }

    public function test_admin_sees_courses_shortcut_flag(): void
    {
        $adminRole = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $this->actingAs($admin);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageCourses', true));
    }
}
