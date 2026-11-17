<?php

namespace Tests\Feature;

use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PreRegistrationPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_can_view_pre_registration_form(): void
    {
        $this->get(route('pre-registration.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pre-registration')
                ->where('status', null));
    }

    public function test_guest_can_submit_pre_registration(): void
    {
        $payload = [
            'name' => 'María Pérez',
            'email' => 'maria@example.com',
            'phone' => '+584121234567',
            'country' => 'VE',
            'message' => 'Quiero clases de bachata los sábados.',
            'agree' => true,
        ];

        $this->post(route('pre-registration.store'), [...$payload, ...$this->preRegistrationFormFields()])
            ->assertRedirect(route('pre-registration.create'));

        $this->assertDatabaseHas('pre_registrations', [
            'name' => 'María Pérez',
            'email' => 'maria@example.com',
            'phone' => '+584121234567',
            'country' => 'VE',
            'message' => 'Quiero clases de bachata los sábados.',
            'agree' => 1,
        ]);
    }

    public function test_country_may_be_omitted(): void
    {
        $this->post(route('pre-registration.store'), [
            ...$this->preRegistrationFormFields(),
            'name' => 'Sin País',
            'email' => 'sinpais@example.com',
            'country' => '',
            'agree' => true,
        ])->assertRedirect(route('pre-registration.create'));

        $this->assertDatabaseHas('pre_registrations', [
            'email' => 'sinpais@example.com',
            'country' => null,
        ]);
    }

    public function test_country_must_be_a_supported_option(): void
    {
        $this->post(route('pre-registration.store'), [
            ...$this->preRegistrationFormFields(),
            'name' => 'País Inválido',
            'email' => 'invalido@example.com',
            'country' => 'XX',
            'agree' => true,
        ])->assertSessionHasErrors('country');

        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_message_and_phone_may_be_omitted(): void
    {
        $this->post(route('pre-registration.store'), [
            ...$this->preRegistrationFormFields(),
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
            'agree' => true,
        ])->assertRedirect(route('pre-registration.create'));

        $this->assertDatabaseHas('pre_registrations', [
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
            'phone' => null,
            'country' => null,
            'message' => null,
            'agree' => 1,
        ]);
    }

    public function test_agree_must_be_accepted(): void
    {
        $this->post(route('pre-registration.store'), [
            ...$this->preRegistrationFormFields(),
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'agree' => false,
        ])->assertSessionHasErrors('agree');

        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_a_repeated_email_gets_the_same_answer_without_duplicates_or_mail(): void
    {
        Mail::fake();
        Notification::fake();
        PreRegistration::factory()->create(['email' => 'dup@example.com', 'name' => 'Original']);

        $this->post(route('pre-registration.store'), [
            ...$this->preRegistrationFormFields(),
            'name' => 'Otro',
            'email' => 'dup@example.com',
            'agree' => true,
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pre-registration.create'))
            ->assertSessionHas('status', 'pre-registration-created');

        $this->assertDatabaseCount('pre_registrations', 1);
        $this->assertDatabaseHas('pre_registrations', ['email' => 'dup@example.com', 'name' => 'Original']);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }
}
