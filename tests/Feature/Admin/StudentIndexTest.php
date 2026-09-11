<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_view_students_index(): void
    {
        $this->get(route('admin.students.index'))->assertRedirect();
    }

    public function test_admin_can_view_students_index(): void
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

        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.students.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/students/index')
                ->has('enrollments')
                ->has('filters')
                ->has('courseOptions')
                ->has('levelOptions')
                ->where('canDeleteStudents', true));
    }

    public function test_students_index_paginates_twenty_per_page(): void
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

        foreach (range(1, 25) as $i) {
            $student = Student::factory()->create();
            Enrollment::factory()->create([
                'course_id' => $course->id,
                'student_id' => $student->id,
            ]);
        }

        $this->actingAs($admin);

        $this->get(route('admin.students.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('enrollments.data', 20)
                ->where('enrollments.per_page', 20)
                ->where('enrollments.total', 25));
    }

    public function test_admin_can_filter_enrollments_by_course(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $courseA = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
        ]);

        $courseB = Course::factory()->create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
        ]);

        Enrollment::factory()->create([
            'course_id' => $courseA->id,
            'student_id' => Student::factory()->create()->id,
        ]);

        Enrollment::factory()->create([
            'course_id' => $courseB->id,
            'student_id' => Student::factory()->create()->id,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.students.index', ['course_id' => $courseA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('enrollments.data', 1));
    }

    public function test_admin_can_store_enrollment_from_enroll_page(): void
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

        $student = Student::factory()->create();

        $this->actingAs($admin);

        $this->post(route('admin.students.enroll.store'), [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ])->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);
    }
}
