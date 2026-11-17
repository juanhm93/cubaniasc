<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\LandingPromotionDisplayMode;
use App\Enums\LandingPromotionOrientation;
use App\Models\LandingPromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LandingPromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_guest_is_redirected_from_promotions(): void
    {
        $this->get(route('admin.promotions.index'))->assertRedirect();
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
    public function test_non_admin_roles_cannot_manage_promotions(string $slug): void
    {
        $user = $this->createUserWithRole($slug);
        $promotion = LandingPromotion::factory()->create(['title' => 'Promo original']);

        $this->actingAs($user);

        $this->get(route('admin.promotions.index'))->assertForbidden();
        $this->post(route('admin.promotions.store'), $this->validPayload())->assertForbidden();
        $this->patch(route('admin.promotions.update', $promotion), $this->validPayload(['title' => 'Otra']))->assertForbidden();
        $this->delete(route('admin.promotions.destroy', $promotion))->assertForbidden();

        $this->assertDatabaseCount('landing_promotions', 1);
        $this->assertSame('Promo original', $promotion->fresh()?->title);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_admin_sees_promotions_with_their_status(): void
    {
        $this->travelTo('2026-11-14 12:00:00');
        $admin = $this->createUserWithRole('admin');
        $active = LandingPromotion::factory()->create([
            'title' => 'Vigente',
            'cta_label' => 'Inscríbete',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
            'display_mode' => LandingPromotionDisplayMode::Section,
            'modal_orientation' => LandingPromotionOrientation::Landscape,
            'updated_at' => now()->subDay(),
        ]);
        LandingPromotion::factory()->inactive()->create(['title' => 'Apagada', 'updated_at' => now()->subDays(2)]);
        LandingPromotion::factory()->expired()->create(['title' => 'Vencida', 'updated_at' => now()->subDays(3)]);
        LandingPromotion::factory()->scheduled()->create(['title' => 'Programada', 'updated_at' => now()->subDays(4)]);

        $this->actingAs($admin);

        $this->get(route('admin.promotions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/promotions/index')
                ->has('promotions', 4)
                ->where('promotions.0.id', $active->id)
                ->where('promotions.0.status', LandingPromotion::STATUS_ACTIVE)
                ->where('promotions.0.cta_label', 'Inscríbete')
                ->where('promotions.0.starts_on', '2026-11-01')
                ->where('promotions.0.ends_on', '2026-11-30')
                ->where('promotions.0.display_mode', 'section')
                ->where('promotions.0.modal_orientation', 'landscape')
                ->where('promotions.0.image_url', Storage::disk('public')->url($active->image_path))
                ->where('promotions.1.status', LandingPromotion::STATUS_INACTIVE)
                ->where('promotions.2.status', LandingPromotion::STATUS_EXPIRED)
                ->where('promotions.3.status', LandingPromotion::STATUS_SCHEDULED));
    }

    public function test_admin_can_create_a_promotion(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin);

        $this->post(route('admin.promotions.store'), $this->validPayload([
            'cta_label' => 'Reserva tu plaza',
            'cta_url' => 'https://wa.me/584140000000',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
            'display_mode' => 'modal',
            'modal_orientation' => 'landscape',
        ]))
            ->assertRedirect(route('admin.promotions.index'))
            ->assertSessionHasNoErrors();

        $promotion = LandingPromotion::query()->sole();

        $this->assertSame('Promo de noviembre', $promotion->title);
        $this->assertSame('Reserva tu plaza', $promotion->cta_label);
        $this->assertSame('https://wa.me/584140000000', $promotion->cta_url);
        $this->assertTrue($promotion->is_active);
        $this->assertSame('2026-11-01', $promotion->starts_on?->toDateString());
        $this->assertSame('2026-11-30', $promotion->ends_on?->toDateString());
        $this->assertSame(LandingPromotionDisplayMode::Modal, $promotion->display_mode);
        $this->assertSame(LandingPromotionOrientation::Landscape, $promotion->modal_orientation);
        $this->assertStringStartsWith('landing/promotions/', $promotion->image_path);
        Storage::disk('public')->assertExists($promotion->image_path);
    }

    public function test_owner_can_create_a_promotion(): void
    {
        $owner = $this->createUserWithRole('staff', ['is_owner' => 1]);

        $this->actingAs($owner);

        $this->post(route('admin.promotions.store'), $this->validPayload())
            ->assertRedirect(route('admin.promotions.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('landing_promotions', 1);
    }

    public function test_update_replaces_the_image_and_deletes_the_previous_one(): void
    {
        $admin = $this->createUserWithRole('admin');
        $previousPath = UploadedFile::fake()->image('old.jpg')->store('landing/promotions', 'public');
        $promotion = LandingPromotion::factory()->create(['image_path' => $previousPath]);

        $this->actingAs($admin);

        $this->patch(route('admin.promotions.update', $promotion), $this->validPayload([
            'title' => 'Promo renovada',
            'is_active' => false,
            'display_mode' => 'section',
        ]))
            ->assertRedirect(route('admin.promotions.index'))
            ->assertSessionHasNoErrors();

        $promotion->refresh();

        $this->assertSame('Promo renovada', $promotion->title);
        $this->assertFalse($promotion->is_active);
        $this->assertSame(LandingPromotionDisplayMode::Section, $promotion->display_mode);
        $this->assertNotSame($previousPath, $promotion->image_path);
        Storage::disk('public')->assertExists($promotion->image_path);
        Storage::disk('public')->assertMissing($previousPath);
    }

    public function test_panel_updates_with_a_new_image_through_method_spoofing(): void
    {
        $admin = $this->createUserWithRole('admin');
        $previousPath = UploadedFile::fake()->image('old.jpg')->store('landing/promotions', 'public');
        $promotion = LandingPromotion::factory()->create(['image_path' => $previousPath]);

        $this->actingAs($admin);

        $this->post(route('admin.promotions.update', $promotion), [
            ...$this->validPayload(['title' => 'Desde el panel']),
            '_method' => 'patch',
        ])
            ->assertRedirect(route('admin.promotions.index'))
            ->assertSessionHasNoErrors();

        $promotion->refresh();

        $this->assertSame('Desde el panel', $promotion->title);
        Storage::disk('public')->assertExists($promotion->image_path);
        Storage::disk('public')->assertMissing($previousPath);
    }

    public function test_update_without_image_keeps_the_current_one(): void
    {
        $admin = $this->createUserWithRole('admin');
        $imagePath = UploadedFile::fake()->image('promo.jpg')->store('landing/promotions', 'public');
        $promotion = LandingPromotion::factory()->create([
            'image_path' => $imagePath,
            'cta_label' => 'Antes',
            'starts_on' => '2026-11-01',
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.promotions.update', $promotion), [
            'title' => 'Sin imagen nueva',
            'cta_label' => '',
            'cta_url' => '',
            'display_mode' => 'both',
            'modal_orientation' => 'portrait',
            'is_active' => true,
            'starts_on' => '',
            'ends_on' => '',
        ])
            ->assertRedirect(route('admin.promotions.index'))
            ->assertSessionHasNoErrors();

        $promotion->refresh();

        $this->assertSame('Sin imagen nueva', $promotion->title);
        $this->assertSame($imagePath, $promotion->image_path);
        $this->assertNull($promotion->cta_label);
        $this->assertNull($promotion->starts_on);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_admin_can_delete_a_promotion_and_its_image(): void
    {
        $admin = $this->createUserWithRole('admin');
        $imagePath = UploadedFile::fake()->image('promo.jpg')->store('landing/promotions', 'public');
        $promotion = LandingPromotion::factory()->create(['image_path' => $imagePath]);

        $this->actingAs($admin);

        $this->delete(route('admin.promotions.destroy', $promotion))
            ->assertRedirect(route('admin.promotions.index'));

        $this->assertModelMissing($promotion);
        Storage::disk('public')->assertMissing($imagePath);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing title' => [['title' => ''], 'title'],
            'missing image' => [['image' => null], 'image'],
            'image is not an image' => [['image' => 'pdf'], 'image'],
            'image too large' => [['image' => 'large'], 'image'],
            'cta url is not a url' => [['cta_url' => 'no-es-un-enlace'], 'cta_url'],
            'cta url is not http' => [['cta_url' => 'javascript:alert(1)'], 'cta_url'],
            'cta label too long' => [['cta_label' => str_repeat('a', 41)], 'cta_label'],
            'missing display mode' => [['display_mode' => null], 'display_mode'],
            'unknown display mode' => [['display_mode' => 'banner'], 'display_mode'],
            'missing modal orientation' => [['modal_orientation' => null], 'modal_orientation'],
            'unknown modal orientation' => [['modal_orientation' => 'diagonal'], 'modal_orientation'],
            'ends before it starts' => [['starts_on' => '2026-11-10', 'ends_on' => '2026-11-09'], 'ends_on'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_store_validates_the_payload(array $overrides, string $field): void
    {
        $admin = $this->createUserWithRole('admin');

        $overrides = match ($overrides['image'] ?? false) {
            'pdf' => [...$overrides, 'image' => UploadedFile::fake()->create('promo.pdf', 100, 'application/pdf')],
            'large' => [...$overrides, 'image' => UploadedFile::fake()->image('promo.jpg')->size(5000)],
            default => $overrides,
        };

        $this->actingAs($admin);

        $this->post(route('admin.promotions.store'), $this->validPayload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('landing_promotions', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_filter([
            'title' => 'Promo de noviembre',
            'image' => UploadedFile::fake()->image('promo.jpg', 1080, 1350),
            'cta_label' => null,
            'cta_url' => null,
            'display_mode' => 'both',
            'modal_orientation' => 'portrait',
            'is_active' => true,
            'starts_on' => null,
            'ends_on' => null,
            ...$overrides,
        ], fn (mixed $value): bool => $value !== null);
    }
}
