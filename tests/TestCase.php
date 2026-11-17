<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use App\Support\PreRegistrationSpamGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createUserWithRole(string $slug, array $attributes = []): User
    {
        $role = Role::factory()->create([
            'slug' => $slug,
            'name' => str_replace('_', ' ', ucwords($slug, '_')),
        ]);

        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            ...$attributes,
        ]);
    }

    /**
     * Anti-spam token of a pre-registration form shown a minute ago, as a person would submit it.
     *
     * @return array<string, string>
     */
    protected function preRegistrationFormFields(int $secondsAgo = 60): array
    {
        return [
            PreRegistrationSpamGuard::TOKEN_FIELD => Crypt::encryptString((string) (now()->timestamp - $secondsAgo)),
        ];
    }
}
