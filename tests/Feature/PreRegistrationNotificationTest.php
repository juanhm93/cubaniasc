<?php

namespace Tests\Feature;

use App\Enums\PreRegistrationCountry;
use App\Mail\PreRegistrationConfirmationMail;
use App\Models\PreRegistration;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewPreRegistrationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PreRegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $role = Role::factory()->create(['slug' => $slug]);

        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            ...$attributes,
        ]);
    }

    private function submitPreRegistration(array $overrides = []): TestResponse
    {
        return $this->post(route('pre-registration.store'), [
            'name' => 'María Pérez',
            'email' => 'maria@example.com',
            'phone' => '+584121234567',
            'country' => 'VE',
            'agree' => true,
            ...$overrides,
        ]);
    }

    public function test_owner_admin_teacher_and_admin_staff_are_notified(): void
    {
        Notification::fake();
        Mail::fake();

        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $adminStaff = $this->userWithRole('admin_staff');

        $this->submitPreRegistration();

        foreach ([$owner, $admin, $teacher, $adminStaff] as $recipient) {
            Notification::assertSentTo($recipient, NewPreRegistrationNotification::class);
        }

        Notification::assertCount(4);
    }

    public function test_plain_staff_and_inactive_users_are_not_notified(): void
    {
        Notification::fake();
        Mail::fake();

        $staff = $this->userWithRole('staff');
        $pendingAdmin = $this->userWithRole('admin', ['status' => 'pending']);
        $roleless = User::factory()->create(['status' => 'active', 'role_id' => null]);

        $this->submitPreRegistration();

        foreach ([$staff, $pendingAdmin, $roleless] as $excluded) {
            Notification::assertNotSentTo($excluded, NewPreRegistrationNotification::class);
        }

        Notification::assertNothingSent();
    }

    public function test_an_owner_who_is_also_admin_is_notified_only_once(): void
    {
        Notification::fake();
        Mail::fake();

        $ownerAdmin = $this->userWithRole('admin', ['is_owner' => 1]);

        $this->submitPreRegistration();

        Notification::assertSentToTimes($ownerAdmin, NewPreRegistrationNotification::class, 1);
        Notification::assertCount(1);
    }

    public function test_applicant_receives_a_confirmation_email(): void
    {
        Notification::fake();
        Mail::fake();

        $this->submitPreRegistration();

        Mail::assertSent(
            PreRegistrationConfirmationMail::class,
            fn (PreRegistrationConfirmationMail $mail): bool => $mail->hasTo('maria@example.com')
        );
    }

    public function test_notifications_are_stored_without_a_queue_worker(): void
    {
        Mail::fake();

        $admin = $this->userWithRole('admin');

        $this->submitPreRegistration();

        $this->assertSame(1, $admin->unreadNotifications()->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_a_mail_failure_still_leaves_the_in_app_notification(): void
    {
        $admin = $this->userWithRole('admin');

        Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

        $this->submitPreRegistration()->assertRedirect(route('pre-registration.create'));

        $this->assertDatabaseHas('pre_registrations', ['email' => 'maria@example.com']);
        $this->assertSame(1, $admin->unreadNotifications()->count());
    }

    public function test_the_staff_notification_carries_every_submitted_field(): void
    {
        Notification::fake();
        Mail::fake();

        $admin = $this->userWithRole('admin');

        $this->submitPreRegistration(['message' => 'Quiero clases de bachata.']);

        Notification::assertSentTo(
            $admin,
            NewPreRegistrationNotification::class,
            function (NewPreRegistrationNotification $notification) use ($admin): bool {
                $payload = $notification->toDatabase($admin);

                return $payload['name'] === 'María Pérez'
                    && $payload['email'] === 'maria@example.com'
                    && $payload['phone'] === '+584121234567'
                    && $payload['country'] === 'VE'
                    && $payload['country_label'] === 'Venezuela'
                    && $payload['submitted_at'] !== null;
            }
        );
    }

    public function test_the_staff_email_renders_with_every_submitted_field(): void
    {
        $admin = $this->userWithRole('admin');

        $pre = PreRegistration::factory()->create([
            'name' => 'María Pérez',
            'email' => 'maria@example.com',
            'phone' => '+584121234567',
            'country' => PreRegistrationCountry::Venezuela,
            'message' => 'Quiero clases de bachata.',
            'agree' => true,
        ]);

        $rendered = (new NewPreRegistrationNotification($pre))->toMail($admin)->render();

        $this->assertStringContainsString('María Pérez', $rendered);
        $this->assertStringContainsString('maria@example.com', $rendered);
        $this->assertStringContainsString('+584121234567', $rendered);
        $this->assertStringContainsString('Venezuela', $rendered);
        $this->assertStringContainsString('Quiero clases de bachata.', $rendered);
        $this->assertStringContainsString(
            e(route('admin.payments.index', ['tab' => 'mas', 'preRegistration' => $pre->id])),
            $rendered
        );
    }

    public function test_the_staff_email_renders_when_optional_fields_are_missing(): void
    {
        $admin = $this->userWithRole('admin');

        $pre = PreRegistration::factory()->create([
            'phone' => null,
            'country' => null,
            'message' => null,
        ]);

        $rendered = (new NewPreRegistrationNotification($pre))->toMail($admin)->render();

        $this->assertStringContainsString('No indicado', $rendered);
        $this->assertStringContainsString('Sin mensaje', $rendered);
    }

    public function test_the_applicant_confirmation_email_renders(): void
    {
        $pre = PreRegistration::factory()->create(['name' => 'María Pérez']);

        $rendered = (new PreRegistrationConfirmationMail($pre))->render();

        $this->assertStringContainsString('María Pérez', $rendered);
        $this->assertStringContainsString('finalizó correctamente', $rendered);
    }

    public function test_an_invalid_submission_sends_nothing(): void
    {
        Notification::fake();
        Mail::fake();

        $this->userWithRole('admin');

        $this->submitPreRegistration(['agree' => false]);

        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }
}
