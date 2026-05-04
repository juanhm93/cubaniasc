<?php

namespace Tests\Feature;

use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'message' => 'Quiero clases de bachata los sábados.',
            'agree' => true,
        ];

        $this->post(route('pre-registration.store'), $payload)
            ->assertRedirect(route('pre-registration.create'));

        $this->assertDatabaseHas('pre_registrations', [
            'name' => 'María Pérez',
            'email' => 'maria@example.com',
            'phone' => '+584121234567',
            'message' => 'Quiero clases de bachata los sábados.',
            'agree' => 1,
        ]);
    }

    public function test_message_and_phone_may_be_omitted(): void
    {
        $this->post(route('pre-registration.store'), [
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
            'agree' => true,
        ])->assertRedirect(route('pre-registration.create'));

        $this->assertDatabaseHas('pre_registrations', [
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
            'phone' => null,
            'message' => null,
            'agree' => 1,
        ]);
    }

    public function test_agree_must_be_accepted(): void
    {
        $this->post(route('pre-registration.store'), [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'agree' => false,
        ])->assertSessionHasErrors('agree');

        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_email_must_be_unique_among_pre_registrations(): void
    {
        PreRegistration::factory()->create(['email' => 'dup@example.com']);

        $this->post(route('pre-registration.store'), [
            'name' => 'Otro',
            'email' => 'dup@example.com',
            'agree' => true,
        ])->assertSessionHasErrors('email');
    }
}
