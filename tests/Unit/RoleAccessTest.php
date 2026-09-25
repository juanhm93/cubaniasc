<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PlatformAbility;
use App\Support\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_has_every_platform_ability(): void
    {
        $user = $this->createUserWithRole('admin');

        foreach (PlatformAbility::cases() as $ability) {
            $this->assertTrue($user->hasAbility($ability));
        }
    }

    public function test_owner_has_every_platform_ability_regardless_of_role(): void
    {
        $user = $this->createUserWithRole('staff', ['is_owner' => 1]);

        foreach (PlatformAbility::cases() as $ability) {
            $this->assertTrue($user->hasAbility($ability));
        }
    }

    public function test_teacher_can_access_content_courses_and_special_classes(): void
    {
        $user = $this->createUserWithRole('teacher');
        $map = RoleAccess::mapFor($user);

        $this->assertSame([
            'content' => true,
            'payments' => false,
            'courses' => true,
            'oneTimeSessions' => true,
            'students' => false,
            'adminUsers' => false,
        ], $map);
    }

    public function test_admin_staff_can_access_payments_courses_special_classes_and_students(): void
    {
        $user = $this->createUserWithRole('admin_staff');
        $map = RoleAccess::mapFor($user);

        $this->assertSame([
            'content' => false,
            'payments' => true,
            'courses' => true,
            'oneTimeSessions' => true,
            'students' => true,
            'adminUsers' => false,
        ], $map);
    }

    public function test_staff_can_access_courses_and_special_classes(): void
    {
        $user = $this->createUserWithRole('staff');
        $map = RoleAccess::mapFor($user);

        $this->assertSame([
            'content' => false,
            'payments' => false,
            'courses' => true,
            'oneTimeSessions' => true,
            'students' => false,
            'adminUsers' => false,
        ], $map);
    }

    public function test_unknown_role_has_no_platform_abilities(): void
    {
        $user = $this->createUserWithRole('guest_role');
        $map = RoleAccess::mapFor($user);

        $this->assertSame([
            'content' => false,
            'payments' => false,
            'courses' => false,
            'oneTimeSessions' => false,
            'students' => false,
            'adminUsers' => false,
        ], $map);
    }
}
