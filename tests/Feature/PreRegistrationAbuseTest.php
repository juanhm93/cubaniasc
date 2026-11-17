<?php

namespace Tests\Feature;

use App\Mail\PreRegistrationConfirmationMail;
use App\Models\PreRegistration;
use App\Models\User;
use App\Notifications\NewPreRegistrationNotification;
use App\Support\PreRegistrationSpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public pre-registration form emails the staff and the applicant, so it
 * must not be usable to flood inboxes, relay phishing links or check who signed up.
 */
class PreRegistrationAbuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        Notification::fake();
    }

    public function test_the_form_carries_an_anti_spam_token(): void
    {
        $this->get(route('pre-registration.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('formToken', fn (string $token): bool => PreRegistrationSpamGuard::tokenIssuedAt($token) !== null));
    }

    public function test_a_filled_honeypot_is_silently_discarded(): void
    {
        $this->post(route('pre-registration.store'), $this->payload([
            PreRegistrationSpamGuard::HONEYPOT_FIELD => 'https://spam.example',
        ]))
            ->assertRedirect(route('pre-registration.create'))
            ->assertSessionHas('status', 'pre-registration-created');

        $this->assertNothingStoredOrSent();
    }

    public function test_a_form_submitted_instantly_is_silently_discarded(): void
    {
        $this->post(route('pre-registration.store'), $this->payload($this->preRegistrationFormFields(secondsAgo: 0)))
            ->assertSessionHas('status', 'pre-registration-created');

        $this->assertNothingStoredOrSent();
    }

    public function test_a_missing_or_forged_token_asks_to_reload_the_form(): void
    {
        $payload = $this->payload();
        unset($payload[PreRegistrationSpamGuard::TOKEN_FIELD]);

        $this->post(route('pre-registration.store'), $payload)
            ->assertSessionHasErrors(PreRegistrationSpamGuard::TOKEN_FIELD);

        $this->post(route('pre-registration.store'), $this->payload([PreRegistrationSpamGuard::TOKEN_FIELD => 'forged']))
            ->assertSessionHasErrors(PreRegistrationSpamGuard::TOKEN_FIELD);

        $this->assertNothingStoredOrSent();
    }

    public function test_names_and_phones_cannot_carry_links(): void
    {
        $this->post(route('pre-registration.store'), $this->payload(['name' => '[Verifica tu pago](https://evil.example)']))
            ->assertSessionHasErrors('name');

        $this->post(route('pre-registration.store'), $this->payload(['phone' => 'https://evil.example']))
            ->assertSessionHasErrors('phone');

        $this->assertNothingStoredOrSent();
    }

    public function test_real_names_and_phones_are_accepted(): void
    {
        $this->post(route('pre-registration.store'), $this->payload([
            'name' => "José María O'Neill-Pérez",
            'phone' => '+58 (412) 123-4567',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pre_registrations', ['name' => "José María O'Neill-Pérez"]);
        Mail::assertSent(PreRegistrationConfirmationMail::class);
    }

    public function test_markdown_in_the_message_does_not_become_a_link_in_the_emails(): void
    {
        $preRegistration = PreRegistration::factory()->create([
            'name' => 'Ana',
            'message' => 'Hola [Paga aquí](https://evil.example) y **urgente**',
        ]);

        $staffMail = (string) (new NewPreRegistrationNotification($preRegistration))
            ->toMail(User::factory()->make())
            ->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $staffMail);
        $this->assertStringNotContainsString('<strong>urgente</strong>', $staffMail);
        $this->assertStringContainsString('[Paga aquí](https://evil.example)', html_entity_decode($staffMail));
    }

    public function test_the_staff_email_subject_does_not_include_the_applicant_text(): void
    {
        $preRegistration = PreRegistration::factory()->create(['name' => 'Ana Spammer']);

        $subject = (new NewPreRegistrationNotification($preRegistration))
            ->toMail(User::factory()->make())
            ->subject;

        $this->assertStringNotContainsString('Ana Spammer', $subject);
    }

    public function test_the_form_is_rate_limited_per_ip(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('pre-registration.store'), $this->payload(['email' => "persona{$attempt}@example.com"]))
                ->assertSessionHasNoErrors();
        }

        $this->post(route('pre-registration.store'), $this->payload(['email' => 'persona4@example.com']))
            ->assertTooManyRequests();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'María Pérez',
            'email' => 'maria@example.com',
            'phone' => '+584121234567',
            'agree' => true,
            ...$this->preRegistrationFormFields(),
            ...$overrides,
        ];
    }

    private function assertNothingStoredOrSent(): void
    {
        $this->assertDatabaseCount('pre_registrations', 0);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }
}
