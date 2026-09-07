<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('account.pending', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_registration_screen_redirects_to_home_when_disabled()
    {
        config(['fortify.registration_enabled' => false]);

        $this->get(route('register'))
            ->assertRedirect(route('home'));
    }

    public function test_registration_cannot_be_submitted_when_disabled()
    {
        config(['fortify.registration_enabled' => false]);

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }
}
