<?php

namespace Tests\Feature\Admin;

use App\Enums\PreRegistrationCountry;
use App\Models\PreRegistration;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PreRegistrationEnrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_view_enroll_form_for_pre_registration(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $pre = PreRegistration::factory()->create();

        $this->actingAs($admin);

        $this->get(
            route('admin.pre-registrations.enroll.create', ['preRegistration' => $pre->id])
        )
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/pre-registrations/enroll')
                ->has('preRegistration')
                ->has('studentDraft')
                ->has('courses'));
    }

    public function test_student_draft_prefills_the_country_from_the_pre_registration(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $pre = PreRegistration::factory()->create([
            'country' => PreRegistrationCountry::Venezuela,
        ]);

        $this->actingAs($admin);

        $this->get(
            route('admin.pre-registrations.enroll.create', ['preRegistration' => $pre->id])
        )
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('studentDraft.country', 'Venezuela'));
    }

    public function test_student_draft_country_is_empty_when_no_country_was_given(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $pre = PreRegistration::factory()->create(['country' => null]);

        $this->actingAs($admin);

        $this->get(
            route('admin.pre-registrations.enroll.create', ['preRegistration' => $pre->id])
        )
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('studentDraft.country', ''));
    }

    public function test_admin_can_create_student_from_pre_registration(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $pre = PreRegistration::factory()->create([
            'name' => 'María Pérez',
            'email' => 'maria.pre@test.invalid',
            'phone' => '+581234567890',
        ]);

        $this->actingAs($admin);

        $response = $this->post(
            route('admin.pre-registrations.enroll.store', ['preRegistration' => $pre->id]),
            [
                'name' => 'María Pérez',
                'email' => 'maria.student@test.invalid',
                'phone' => '+581234567890',
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
            ]
        );

        $this->assertDatabaseHas('students', [
            'name' => 'María Pérez',
            'email' => 'maria.student@test.invalid',
        ]);

        $student = Student::query()->where('email', 'maria.student@test.invalid')->firstOrFail();

        $response->assertRedirect(route('admin.students.show', $student));

        $this->assertSoftDeleted('pre_registrations', [
            'id' => $pre->id,
        ]);
    }

    public function test_non_admin_cannot_access_enroll_form(): void
    {
        $role = Role::factory()->create(['slug' => 'teacher']);
        $user = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $pre = PreRegistration::factory()->create();

        $this->actingAs($user);

        $this->get(
            route('admin.pre-registrations.enroll.create', ['preRegistration' => $pre->id])
        )->assertForbidden();
    }
}
