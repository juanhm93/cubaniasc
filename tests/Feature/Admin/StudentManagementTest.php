<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_update_or_delete_a_student(): void
    {
        $student = Student::factory()->create();

        $this->patch(route('admin.students.update', $student), $this->studentPayload($student, [
            'name' => 'Nuevo nombre',
        ]))->assertRedirect();

        $this->delete(route('admin.students.destroy', $student))->assertRedirect();

        $this->assertNotSoftDeleted($student);
        $this->assertSame($student->name, $student->fresh()?->name);
    }

    public function test_teacher_cannot_update_or_delete_a_student(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $student = Student::factory()->create();

        $this->actingAs($teacher);

        $this->patch(route('admin.students.update', $student), $this->studentPayload($student, [
            'name' => 'Nuevo nombre',
        ]))->assertForbidden();

        $this->delete(route('admin.students.destroy', $student))->assertForbidden();

        $this->assertNotSoftDeleted($student);
        $this->assertSame($student->name, $student->fresh()?->name);
    }

    public function test_admin_show_page_allows_email_updates(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = Student::factory()->create();

        $this->actingAs($admin);

        $this->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/students/show')
                ->where('canUpdateEmail', true)
                ->where('student.id', $student->id));
    }

    public function test_admin_staff_show_page_does_not_allow_email_updates(): void
    {
        $staff = $this->createUserWithRole('admin_staff');
        $student = Student::factory()->create();

        $this->actingAs($staff);

        $this->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/students/show')
                ->where('canUpdateEmail', false));
    }

    public function test_admin_can_update_student_including_email(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = Student::factory()->create([
            'name' => 'Nombre original',
            'email' => 'original@example.com',
            'phone' => '+580000000000',
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.students.update', $student), $this->studentPayload($student, [
            'name' => 'Nombre actualizado',
            'email' => 'nuevo@example.com',
            'phone' => '+581111111111',
            'city' => 'Caracas',
        ]))->assertRedirect(route('admin.students.show', $student));

        $student->refresh();

        $this->assertSame('Nombre actualizado', $student->name);
        $this->assertSame('nuevo@example.com', $student->email);
        $this->assertSame('+581111111111', $student->phone);
        $this->assertSame('Caracas', $student->city);
    }

    public function test_owner_can_update_student_email(): void
    {
        $owner = $this->createUserWithRole('staff', ['is_owner' => 1]);
        $student = Student::factory()->create([
            'email' => 'owner-original@example.com',
        ]);

        $this->actingAs($owner);

        $this->patch(route('admin.students.update', $student), $this->studentPayload($student, [
            'email' => 'owner-nuevo@example.com',
        ]))->assertRedirect(route('admin.students.show', $student));

        $this->assertSame('owner-nuevo@example.com', $student->fresh()->email);
    }

    public function test_admin_staff_can_update_student_fields_but_not_email(): void
    {
        $staff = $this->createUserWithRole('admin_staff');
        $student = Student::factory()->create([
            'name' => 'Staff original',
            'email' => 'staff-original@example.com',
            'phone' => '+582222222222',
        ]);

        $this->actingAs($staff);

        $this->patch(route('admin.students.update', $student), $this->studentPayload($student, [
            'name' => 'Staff actualizado',
            'email' => 'staff-hack@example.com',
            'phone' => '+583333333333',
            'address' => 'Calle 8',
        ]))->assertRedirect(route('admin.students.show', $student));

        $student->refresh();

        $this->assertSame('Staff actualizado', $student->name);
        $this->assertSame('staff-original@example.com', $student->email);
        $this->assertSame('+583333333333', $student->phone);
        $this->assertSame('Calle 8', $student->address);
    }

    public function test_admin_cannot_update_student_to_duplicate_email(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = Student::factory()->create();
        $other = Student::factory()->create([
            'email' => 'taken@example.com',
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.students.update', $student), $this->studentPayload($student, [
            'email' => $other->email,
        ]))->assertSessionHasErrors('email');

        $this->assertNotSame($other->email, $student->fresh()->email);
    }

    public function test_name_is_required_when_updating_a_student(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = Student::factory()->create([
            'name' => 'Nombre requerido',
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.students.update', $student), $this->studentPayload($student, [
            'name' => '',
        ]))->assertSessionHasErrors('name');

        $this->assertSame('Nombre requerido', $student->fresh()->name);
    }

    public function test_admin_staff_index_does_not_expose_delete_action(): void
    {
        $staff = $this->createUserWithRole('admin_staff');

        $this->actingAs($staff);

        $this->get(route('admin.students.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/students/index')
                ->where('canDeleteStudents', false));
    }

    public function test_admin_can_soft_delete_a_student(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createEnrolledStudentFor($admin);

        $this->actingAs($admin);

        $this->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertSoftDeleted($student);
    }

    public function test_owner_can_soft_delete_a_student(): void
    {
        $owner = $this->createUserWithRole('staff', ['is_owner' => 1]);
        $student = $this->createEnrolledStudentFor($owner);

        $this->actingAs($owner);

        $this->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertSoftDeleted($student);
    }

    public function test_admin_staff_cannot_delete_a_student(): void
    {
        $staff = $this->createUserWithRole('admin_staff');
        $student = Student::factory()->create();

        $this->actingAs($staff);

        $this->delete(route('admin.students.destroy', $student))->assertForbidden();

        $this->assertNotSoftDeleted($student);
    }

    public function test_soft_deleted_students_are_hidden_from_the_index(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createEnrolledStudentFor($admin);

        $this->actingAs($admin);

        $this->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->get(route('admin.students.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('enrollments.total', 0)
                ->has('enrollments.data', 0));

        $this->get(route('admin.students.show', $student))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function studentPayload(Student $student, array $overrides = []): array
    {
        return [
            'name' => $student->name,
            'email' => $student->email,
            'dni' => $student->dni,
            'birthday' => $student->birthday?->format('Y-m-d'),
            'phone' => $student->phone,
            'address' => $student->address,
            'city' => $student->city,
            'state' => $student->state,
            'zip' => $student->zip,
            'country' => $student->country,
            'emergency_contact_name' => $student->emergency_contact_name,
            'emergency_contact_phone' => $student->emergency_contact_phone,
            ...$overrides,
        ];
    }

    private function createEnrolledStudentFor(User $user): Student
    {
        $course = Course::factory()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
        ]);

        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        return $student;
    }
}
