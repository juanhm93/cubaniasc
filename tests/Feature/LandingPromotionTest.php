<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LandingPromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        config(['cubania.timezone' => 'America/Caracas']);
        $this->travelTo('2026-11-14 12:00:00');
    }

    public function test_landing_has_no_promotion_when_none_exist(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('promotion', null));
    }

    public function test_inactive_expired_and_future_promotions_are_hidden(): void
    {
        LandingPromotion::factory()->inactive()->create();
        LandingPromotion::factory()->expired()->create();
        LandingPromotion::factory()->scheduled()->create();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('promotion', null));
    }

    public function test_active_promotion_without_dates_is_shown(): void
    {
        $promotion = LandingPromotion::factory()->create([
            'title' => 'Clase gratis',
            'cta_label' => 'Escríbenos',
            'cta_url' => 'https://wa.me/584140000000',
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotion.id', $promotion->id)
                ->where('promotion.title', 'Clase gratis')
                ->where('promotion.imageUrl', Storage::disk('public')->url($promotion->image_path))
                ->where('promotion.ctaLabel', 'Escríbenos')
                ->where('promotion.ctaUrl', 'https://wa.me/584140000000')
                ->where('promotion.showModal', true)
                ->where('promotion.showSection', true)
                ->where('promotion.modalOrientation', 'portrait')
                ->where('promotion.version', $promotion->updated_at->getTimestamp()));
    }

    public function test_modal_only_promotion_has_no_section(): void
    {
        LandingPromotion::factory()->modalOnly()->landscape()->create();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotion.showModal', true)
                ->where('promotion.showSection', false)
                ->where('promotion.modalOrientation', 'landscape'));
    }

    public function test_section_only_promotion_has_no_modal(): void
    {
        LandingPromotion::factory()->sectionOnly()->create();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotion.showModal', false)
                ->where('promotion.showSection', true));
    }

    public function test_landing_still_renders_when_the_promotions_table_is_missing(): void
    {
        // Code uploaded by FTP before the migration ran from /admin/maintenance.
        Schema::drop('landing_promotions');

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('promotion', null));
    }

    public function test_promotion_without_display_columns_falls_back_to_defaults(): void
    {
        // Database created before display_mode and modal_orientation existed.
        $promotion = LandingPromotion::factory()->create(['is_active' => true, 'starts_on' => null, 'ends_on' => null]);
        Schema::table('landing_promotions', fn ($table) => $table->dropColumn(['display_mode', 'modal_orientation']));

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotion.id', $promotion->id)
                ->where('promotion.showModal', true)
                ->where('promotion.showSection', true)
                ->where('promotion.modalOrientation', 'portrait'));
    }

    public function test_promotion_is_shown_on_its_first_and_last_day(): void
    {
        $promotion = LandingPromotion::factory()->create([
            'starts_on' => '2026-11-14',
            'ends_on' => '2026-11-14',
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('promotion.id', $promotion->id));
    }

    public function test_dates_use_the_academy_timezone(): void
    {
        $promotion = LandingPromotion::factory()->create(['ends_on' => '2026-11-14']);

        // 02:00 UTC on the 15th is still the 14th in Caracas (UTC-4).
        $this->travelTo('2026-11-15 02:00:00');

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('promotion.id', $promotion->id));

        $this->travelTo('2026-11-15 05:00:00');

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('promotion', null));
    }

    public function test_the_dated_promotion_with_the_latest_start_wins(): void
    {
        LandingPromotion::factory()->create(['title' => 'Sin fechas', 'updated_at' => now()]);
        LandingPromotion::factory()->create(['title' => 'Desde el 1', 'starts_on' => '2026-11-01']);
        $latest = LandingPromotion::factory()->create(['title' => 'Desde el 10', 'starts_on' => '2026-11-10', 'updated_at' => now()->subWeek()]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('promotion.id', $latest->id));
    }

    public function test_most_recently_edited_wins_among_undated_promotions(): void
    {
        LandingPromotion::factory()->create(['updated_at' => now()->subDay()]);
        $recent = LandingPromotion::factory()->create(['updated_at' => now()]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('promotion.id', $recent->id));
    }

    public function test_cta_label_without_url_links_to_pre_registration(): void
    {
        LandingPromotion::factory()->create(['cta_label' => 'Preinscríbete', 'cta_url' => null]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotion.ctaLabel', 'Preinscríbete')
                ->where('promotion.ctaUrl', route('pre-registration.create', absolute: false)));
    }

    public function test_promotion_without_cta_label_has_no_button(): void
    {
        LandingPromotion::factory()->create(['cta_label' => null, 'cta_url' => 'https://example.com']);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotion.ctaLabel', null)
                ->where('promotion.ctaUrl', null));
    }
}
