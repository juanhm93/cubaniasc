<?php

namespace Database\Factories;

use App\Enums\LandingPromotionDisplayMode;
use App\Enums\LandingPromotionOrientation;
use App\Models\LandingPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LandingPromotion>
 */
class LandingPromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Promo '.fake()->words(2, true),
            'image_path' => 'landing/promotions/'.fake()->uuid().'.jpg',
            'cta_label' => null,
            'cta_url' => null,
            'display_mode' => LandingPromotionDisplayMode::Both,
            'modal_orientation' => LandingPromotionOrientation::Portrait,
            'is_active' => true,
            'starts_on' => null,
            'ends_on' => null,
        ];
    }

    public function modalOnly(): static
    {
        return $this->state(fn (): array => ['display_mode' => LandingPromotionDisplayMode::Modal]);
    }

    public function sectionOnly(): static
    {
        return $this->state(fn (): array => ['display_mode' => LandingPromotionDisplayMode::Section]);
    }

    public function landscape(): static
    {
        return $this->state(fn (): array => ['modal_orientation' => LandingPromotionOrientation::Landscape]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->subDays(2)->toDateString(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(10)->toDateString(),
        ]);
    }
}
