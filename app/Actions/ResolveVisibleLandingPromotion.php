<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LandingPromotionDisplayMode;
use App\Enums\LandingPromotionOrientation;
use App\Models\LandingPromotion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Picks the promotion the public landing shows today.
 *
 * Among the active promotions whose dates include today (academy timezone), the one with
 * the latest start date wins, so a dated campaign overrides an undated default one. Ties
 * go to the most recently edited.
 */
class ResolveVisibleLandingPromotion
{
    /**
     * @return array{id: int, title: string, imageUrl: string, ctaLabel: string|null, ctaUrl: string|null, showModal: bool, showSection: bool, modalOrientation: string, version: int}|null
     */
    public function handle(): ?array
    {
        $promotion = $this->findVisiblePromotion();

        if ($promotion === null) {
            return null;
        }

        $displayMode = $promotion->display_mode ?? LandingPromotionDisplayMode::Both;
        $orientation = $promotion->modal_orientation ?? LandingPromotionOrientation::Portrait;

        return [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'imageUrl' => $promotion->image_url,
            'ctaLabel' => $promotion->cta_label,
            'ctaUrl' => $this->resolveCtaUrl($promotion),
            'showModal' => $displayMode->showsModal(),
            'showSection' => $displayMode->showsSection(),
            'modalOrientation' => $orientation->value,
            'version' => (int) $promotion->updated_at?->getTimestamp(),
        ];
    }

    /**
     * A database failure (for example, code deployed before its migration ran on the
     * hosting) is reported and the landing renders without a promotion instead of failing.
     */
    private function findVisiblePromotion(): ?LandingPromotion
    {
        $today = Carbon::now(config('cubania.timezone'));

        try {
            return LandingPromotion::query()
                ->visibleOn($today)
                ->orderByRaw('starts_on is null')
                ->orderByDesc('starts_on')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
        } catch (QueryException $exception) {
            report($exception);

            return null;
        }
    }

    private function resolveCtaUrl(LandingPromotion $promotion): ?string
    {
        if ($promotion->cta_label === null) {
            return null;
        }

        return $promotion->cta_url ?? route('pre-registration.create', absolute: false);
    }
}
