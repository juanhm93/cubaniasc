<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleNavigationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_teacher_receives_content_course_and_special_class_abilities(): void
    {
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.abilities.content', true)
                ->where('auth.abilities.payments', false)
                ->where('auth.abilities.courses', true)
                ->where('auth.abilities.oneTimeSessions', true)
                ->where('auth.abilities.students', false)
                ->where('auth.abilities.adminUsers', false)
                ->where('canManageCourses', true));
    }

    public function test_admin_staff_receives_payments_course_special_class_and_student_abilities(): void
    {
        $this->actingAs($this->createUserWithRole('admin_staff'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.abilities.content', false)
                ->where('auth.abilities.payments', true)
                ->where('auth.abilities.courses', true)
                ->where('auth.abilities.oneTimeSessions', true)
                ->where('auth.abilities.students', true)
                ->where('auth.abilities.adminUsers', false));
    }

    public function test_staff_receives_course_and_special_class_abilities(): void
    {
        $this->actingAs($this->createUserWithRole('staff'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.abilities.content', false)
                ->where('auth.abilities.payments', false)
                ->where('auth.abilities.courses', true)
                ->where('auth.abilities.oneTimeSessions', true)
                ->where('auth.abilities.students', false)
                ->where('auth.abilities.adminUsers', false));
    }

    public function test_teacher_can_open_assigned_sections_and_is_blocked_from_the_rest(): void
    {
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->get(route('content.index'))->assertOk();
        $this->get(route('admin.courses.index'))->assertOk();
        $this->get(route('admin.one-time-sessions.index'))->assertOk();
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.students.index'))->assertForbidden();
        $this->get(route('admin.payments.enroll.create'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_staff_can_open_assigned_sections_and_is_blocked_from_the_rest(): void
    {
        $this->actingAs($this->createUserWithRole('admin_staff'));

        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.courses.index'))->assertOk();
        $this->get(route('admin.one-time-sessions.index'))->assertOk();
        $this->get(route('admin.students.index'))->assertOk();
        $this->get(route('admin.payments.enroll.create'))->assertOk();
        $this->get(route('content.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_staff_can_open_assigned_sections_and_is_blocked_from_the_rest(): void
    {
        $this->actingAs($this->createUserWithRole('staff'));

        $this->get(route('admin.courses.index'))->assertOk();
        $this->get(route('admin.one-time-sessions.index'))->assertOk();
        $this->get(route('content.index'))->assertForbidden();
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.students.index'))->assertForbidden();
        $this->get(route('admin.payments.enroll.create'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_open_every_platform_section(): void
    {
        $this->actingAs($this->createUserWithRole('admin'));

        $this->get(route('content.index'))->assertOk();
        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.courses.index'))->assertOk();
        $this->get(route('admin.one-time-sessions.index'))->assertOk();
        $this->get(route('admin.students.index'))->assertOk();
        $this->get(route('admin.payments.enroll.create'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_owner_without_admin_role_can_open_every_platform_section(): void
    {
        $this->actingAs($this->createUserWithRole('staff', ['is_owner' => 1]));

        $this->get(route('content.index'))->assertOk();
        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.courses.index'))->assertOk();
        $this->get(route('admin.one-time-sessions.index'))->assertOk();
        $this->get(route('admin.students.index'))->assertOk();
        $this->get(route('admin.payments.enroll.create'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
    }
}
