<?php

namespace App\Models;

use App\Enums\LandingPromotionDisplayMode;
use App\Enums\LandingPromotionOrientation;
use Carbon\CarbonInterface;
use Database\Factories\LandingPromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Promotion image shown on the public landing while it is active and within its dates.
 */
#[Fillable(['title', 'image_path', 'cta_label', 'cta_url', 'display_mode', 'modal_orientation', 'is_active', 'starts_on', 'ends_on'])]
class LandingPromotion extends Model
{
    /** @use HasFactory<LandingPromotionFactory> */
    use HasFactory;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_SCHEDULED = 'scheduled';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_INACTIVE = 'inactive';

    /**
     * Public URL for the stored image (requires `storage:link` and `public` disk).
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk('public')->url($this->image_path));
    }

    /**
     * Active promotions whose optional date range includes the given day.
     *
     * @param  Builder<LandingPromotion>  $query
     */
    public function scopeVisibleOn(Builder $query, CarbonInterface $date): void
    {
        $day = $date->toDateString();

        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $day))
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day));
    }

    /**
     * Status of the promotion on the given day, for the admin list.
     */
    public function statusOn(CarbonInterface $date): string
    {
        $day = $date->toDateString();

        if (! $this->is_active) {
            return self::STATUS_INACTIVE;
        }

        if ($this->starts_on !== null && $this->starts_on->toDateString() > $day) {
            return self::STATUS_SCHEDULED;
        }

        if ($this->ends_on !== null && $this->ends_on->toDateString() < $day) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_mode' => LandingPromotionDisplayMode::class,
            'modal_orientation' => LandingPromotionOrientation::class,
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
