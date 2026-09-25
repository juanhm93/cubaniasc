<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingAuthVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_shows_login_and_register_when_enabled(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('canLogin', true)
                ->where('canRegister', true));
    }

    public function test_landing_hides_register_when_registration_is_disabled(): void
    {
        config(['fortify.registration_enabled' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('canRegister', false)
                ->where('canLogin', true));
    }

    public function test_landing_hides_login_when_login_is_not_visible(): void
    {
        config(['fortify.login_visible' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('canLogin', false)
                ->where('canRegister', true));
    }

    public function test_pre_registration_page_receives_auth_visibility_flags(): void
    {
        config([
            'fortify.registration_enabled' => false,
            'fortify.login_visible' => false,
        ]);

        $this->get(route('pre-registration.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pre-registration')
                ->where('canLogin', false)
                ->where('canRegister', false));
    }

    public function test_login_screen_remains_available_when_hidden_from_landing(): void
    {
        config(['fortify.login_visible' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('canLogin', false));
    }

    public function test_login_screen_hides_register_link_when_registration_is_disabled(): void
    {
        config(['fortify.registration_enabled' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('canRegister', false));
    }

    public function test_users_can_still_authenticate_when_login_is_hidden_from_landing(): void
    {
        config(['fortify.login_visible' => false]);

        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }
}
