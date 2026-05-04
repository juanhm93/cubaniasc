<?php

namespace Tests\Feature\Settings;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_company_settings(): void
    {
        $this->get(route('settings.company.edit'))
            ->assertRedirect();
    }

    public function test_standard_user_cannot_view_company_settings(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'is_owner' => 0,
            'company_id' => null,
            'role_id' => Role::factory()->create([
                'slug' => 'teacher',
                'name' => 'Teacher',
            ])->id,
        ]);

        $this->actingAs($user);

        $this->get(route('settings.company.edit'))->assertForbidden();
    }

    public function test_admin_can_view_company_settings_without_edit_flag(): void
    {
        $company = Company::factory()->create([
            'name' => 'Cubania',
            'slug' => 'cubania',
            'email' => 'info@cubania.test',
        ]);

        $user = User::factory()->create([
            'status' => 'active',
            'is_owner' => 0,
            'company_id' => null,
            'role_id' => Role::factory()->create([
                'slug' => 'admin',
                'name' => 'Admin',
            ])->id,
        ]);

        $this->actingAs($user);

        $this->get(route('settings.company.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/company')
                ->where('canEdit', false)
                ->where('company.id', $company->id));
    }

    public function test_owner_can_view_company_settings_with_edit_flag(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'status' => 'active',
            'is_owner' => 1,
            'company_id' => $company->id,
            'role_id' => Role::factory()->create([
                'slug' => 'admin',
                'name' => 'Admin',
            ])->id,
        ]);

        $this->actingAs($user);

        $this->get(route('settings.company.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/company')
                ->where('canEdit', true)
                ->where('company.id', $company->id));
    }

    public function test_owner_without_company_can_create_company(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'is_owner' => 1,
            'company_id' => null,
            'role_id' => Role::factory()->create()->id,
        ]);

        $this->actingAs($user);

        $this->patch(route('settings.company.update'), [
            'name' => 'Academy Co',
            'email' => 'hello@academy.test',
            'is_active' => true,
        ])
            ->assertRedirect(route('settings.company.edit'));

        $this->assertDatabaseHas('companies', [
            'name' => 'Academy Co',
            'email' => 'hello@academy.test',
        ]);

        $user->refresh();

        $this->assertNotNull($user->company_id);
        $this->assertSame('Academy Co', $user->company?->name);
    }

    public function test_owner_can_update_existing_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Old',
            'slug' => 'old',
            'email' => 'old@example.test',
        ]);

        $user = User::factory()->create([
            'status' => 'active',
            'is_owner' => 1,
            'company_id' => $company->id,
            'role_id' => Role::factory()->create()->id,
        ]);

        $this->actingAs($user);

        $this->patch(route('settings.company.update'), [
            'name' => 'New Name',
            'slug' => 'new-name',
            'email' => 'new@example.test',
            'phone' => '+580000000000',
            'is_active' => false,
        ])
            ->assertRedirect(route('settings.company.edit'));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'New Name',
            'slug' => 'new-name',
            'email' => 'new@example.test',
            'phone' => '+580000000000',
        ]);
    }

    public function test_owner_can_upload_company_logo(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create([
            'logo' => null,
        ]);

        $user = User::factory()->create([
            'status' => 'active',
            'is_owner' => 1,
            'company_id' => $company->id,
            'role_id' => Role::factory()->create()->id,
        ]);

        $file = UploadedFile::fake()->image('logo.png', 120, 120);

        $this->actingAs($user);

        $this->patch(route('settings.company.update'), [
            'name' => $company->name,
            'slug' => $company->slug,
            'email' => $company->email,
            'is_active' => true,
            'logo' => $file,
        ])->assertRedirect(route('settings.company.edit'));

        $company->refresh();

        $this->assertNotNull($company->logo);
        Storage::disk('public')->assertExists($company->logo);
    }

    public function test_admin_cannot_update_company_settings(): void
    {
        Company::factory()->create([
            'email' => 'company@example.test',
        ]);

        $user = User::factory()->create([
            'status' => 'active',
            'is_owner' => 0,
            'company_id' => null,
            'role_id' => Role::factory()->create([
                'slug' => 'admin',
                'name' => 'Admin',
            ])->id,
        ]);

        $this->actingAs($user);

        $this->patch(route('settings.company.update'), [
            'name' => 'Hacked',
            'email' => 'hacked@example.test',
        ])->assertForbidden();
    }
}
