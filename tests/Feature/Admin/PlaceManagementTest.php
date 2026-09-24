<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Course;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlaceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_places(): void
    {
        $this->get(route('admin.places.index'))->assertRedirect();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function restrictedRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'admin_staff' => ['admin_staff'],
            'staff' => ['staff'],
        ];
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_admin_roles_cannot_manage_places(string $slug): void
    {
        $company = Company::factory()->create();
        $user = $this->createUserWithRole($slug, ['company_id' => $company->id]);
        $place = Place::factory()->for($company)->create(['name' => 'Sala Original']);

        $this->actingAs($user);

        $this->get(route('admin.places.index'))->assertForbidden();
        $this->post(route('admin.places.store'), ['name' => 'Sala Nueva'])->assertForbidden();
        $this->patch(route('admin.places.update', $place), ['name' => 'Otro'])->assertForbidden();
        $this->delete(route('admin.places.destroy', $place))->assertForbidden();

        $this->assertDatabaseMissing('places', ['name' => 'Sala Nueva']);
        $this->assertSame('Sala Original', $place->fresh()?->name);
        $this->assertNotSoftDeleted($place);
    }

    public function test_admin_sees_only_places_of_their_company(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUserWithRole('admin', ['company_id' => $company->id]);
        $place = Place::factory()->for($company)->create(['name' => 'Sala Centro']);
        Course::factory()->create(['company_id' => $company->id, 'place_id' => $place->id]);
        Place::factory()->create(['name' => 'Sala Ajena']);

        $this->actingAs($admin);

        $this->get(route('admin.places.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/places/index')
                ->has('places', 1)
                ->where('places.0.id', $place->id)
                ->where('places.0.name', 'Sala Centro')
                ->where('places.0.courses_count', 1));
    }

    public function test_admin_can_create_a_place_for_their_company(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUserWithRole('admin', ['company_id' => $company->id]);

        $this->actingAs($admin);

        $this->post(route('admin.places.store'), [
            'name' => 'Sala Norte',
            'address' => 'Calle 1',
            'phone' => '',
        ])->assertRedirect(route('admin.places.index'));

        $this->assertDatabaseHas('places', [
            'company_id' => $company->id,
            'name' => 'Sala Norte',
            'address' => 'Calle 1',
            'phone' => null,
        ]);
    }

    public function test_owner_can_create_a_place(): void
    {
        $company = Company::factory()->create();
        $owner = $this->createUserWithRole('staff', [
            'company_id' => $company->id,
            'is_owner' => 1,
        ]);

        $this->actingAs($owner);

        $this->post(route('admin.places.store'), ['name' => 'Sala Owner'])
            ->assertRedirect(route('admin.places.index'));

        $this->assertDatabaseHas('places', [
            'company_id' => $company->id,
            'name' => 'Sala Owner',
        ]);
    }

    public function test_admin_without_company_cannot_create_a_place(): void
    {
        $admin = $this->createUserWithRole('admin', ['company_id' => null]);

        $this->actingAs($admin);

        $this->post(route('admin.places.store'), ['name' => 'Sala Huérfana'])
            ->assertForbidden();

        $this->assertDatabaseMissing('places', ['name' => 'Sala Huérfana']);
    }

    public function test_place_creation_is_validated(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUserWithRole('admin', ['company_id' => $company->id]);
        Place::factory()->for($company)->create(['name' => 'Sala Repetida']);

        $this->actingAs($admin);

        $this->post(route('admin.places.store'), [
            'name' => '',
            'phone' => str_repeat('1', 40),
        ])->assertSessionHasErrors(['name', 'phone']);

        $this->post(route('admin.places.store'), ['name' => 'Sala Repetida'])
            ->assertSessionHasErrors(['name']);

        $this->assertSame(1, Place::query()->count());
    }

    public function test_admin_can_update_a_place(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUserWithRole('admin', ['company_id' => $company->id]);
        $place = Place::factory()->for($company)->create(['name' => 'Sala Vieja']);

        $this->actingAs($admin);

        $this->patch(route('admin.places.update', $place), [
            'name' => 'Sala Vieja',
            'address' => 'Avenida 2',
            'phone' => '600000000',
        ])->assertRedirect(route('admin.places.index'));

        $place->refresh();

        $this->assertSame('Sala Vieja', $place->name);
        $this->assertSame('Avenida 2', $place->address);
        $this->assertSame('600000000', $place->phone);
    }

    public function test_admin_cannot_update_or_delete_a_place_from_another_company(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUserWithRole('admin', ['company_id' => $company->id]);
        $foreignPlace = Place::factory()->create(['name' => 'Sala Ajena']);

        $this->actingAs($admin);

        $this->patch(route('admin.places.update', $foreignPlace), ['name' => 'Cambiada'])
            ->assertForbidden();
        $this->delete(route('admin.places.destroy', $foreignPlace))
            ->assertForbidden();

        $this->assertSame('Sala Ajena', $foreignPlace->fresh()?->name);
        $this->assertNotSoftDeleted($foreignPlace);
    }

    public function test_admin_can_delete_an_unused_place(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUserWithRole('admin', ['company_id' => $company->id]);
        $place = Place::factory()->for($company)->create();

        $this->actingAs($admin);

        $this->delete(route('admin.places.destroy', $place))
            ->assertRedirect(route('admin.places.index'));

        $this->assertSoftDeleted($place);
    }

    public function test_place_with_courses_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUserWithRole('admin', ['company_id' => $company->id]);
        $place = Place::factory()->for($company)->create();
        Course::factory()->create(['company_id' => $company->id, 'place_id' => $place->id]);

        $this->actingAs($admin);

        $this->delete(route('admin.places.destroy', $place))
            ->assertRedirect(route('admin.places.index'));

        $this->assertNotSoftDeleted($place);
    }
}
