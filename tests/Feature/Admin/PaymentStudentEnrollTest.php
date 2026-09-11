<?php

namespace Tests\Feature\Admin;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentStudentEnrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_view_enroll_form_from_payments(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        Course::factory()->create();

        $this->actingAs($admin);

        $this->get(route('admin.payments.enroll.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/payments/enroll')
                ->has('courses', 1));
    }

    public function test_admin_can_create_student_without_course(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.payments.enroll.store'), [
            'name' => 'Carlos Ruiz',
            'email' => 'carlos.walkin@test.invalid',
            'phone' => '+584121234567',
            'dni' => '',
            'birthday' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'course_id' => '',
        ]);

        $student = Student::query()->where('email', 'carlos.walkin@test.invalid')->firstOrFail();

        $this->assertSame('Carlos Ruiz', $student->name);
        $this->assertSame('+584121234567', $student->phone);
        $this->assertSame(0, Enrollment::query()->where('student_id', $student->id)->count());

        $response->assertRedirect(route('admin.students.show', $student));
    }

    public function test_admin_can_create_student_and_enroll_in_course(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $course = Course::factory()->create();

        $this->actingAs($admin);

        $response = $this->post(route('admin.payments.enroll.store'), [
            'name' => 'Ana Gómez',
            'email' => 'ana.walkin@test.invalid',
            'phone' => '',
            'dni' => 'V12345678',
            'birthday' => '1990-05-12',
            'address' => 'Calle 1',
            'city' => 'Caracas',
            'state' => 'DC',
            'zip' => '1010',
            'country' => 'VE',
            'emergency_contact_name' => 'Luis Gómez',
            'emergency_contact_phone' => '+584149998877',
            'course_id' => $course->id,
        ]);

        $student = Student::query()->where('email', 'ana.walkin@test.invalid')->firstOrFail();

        $this->assertSame('Ana Gómez', $student->name);
        $this->assertSame('V12345678', $student->dni);
        $this->assertSame('Calle 1', $student->address);
        $this->assertSame('Luis Gómez', $student->emergency_contact_name);

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

        $response->assertRedirect(route('admin.students.show', $student));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        Student::factory()->create([
            'email' => 'taken@test.invalid',
        ]);

        $this->actingAs($admin);

        $this->from(route('admin.payments.enroll.create'))
            ->post(route('admin.payments.enroll.store'), [
                'name' => 'Otro Alumno',
                'email' => 'taken@test.invalid',
                'phone' => '',
                'dni' => '',
                'birthday' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country' => '',
                'emergency_contact_name' => '',
                'emergency_contact_phone' => '',
                'course_id' => '',
            ])
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('admin.payments.enroll.create'));

        $this->assertSame(1, Student::query()->where('email', 'taken@test.invalid')->count());
    }

    public function test_name_and_email_are_required(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);

        $this->from(route('admin.payments.enroll.create'))
            ->post(route('admin.payments.enroll.store'), [
                'name' => '',
                'email' => '',
            ])
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_invalid_course_is_rejected(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);

        $this->from(route('admin.payments.enroll.create'))
            ->post(route('admin.payments.enroll.store'), [
                'name' => 'Carlos Ruiz',
                'email' => 'carlos.invalid-course@test.invalid',
                'course_id' => 999999,
            ])
            ->assertSessionHasErrors('course_id');

        $this->assertDatabaseMissing('students', [
            'email' => 'carlos.invalid-course@test.invalid',
        ]);
    }

    public function test_non_admin_cannot_access_enroll_form(): void
    {
        $role = Role::factory()->create(['slug' => 'teacher']);
        $user = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        $this->get(route('admin.payments.enroll.create'))->assertForbidden();
    }

    public function test_guest_is_redirected_from_enroll_form(): void
    {
        $this->get(route('admin.payments.enroll.create'))->assertRedirect();
    }
}
