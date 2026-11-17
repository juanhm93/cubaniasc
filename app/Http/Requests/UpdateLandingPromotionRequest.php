<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Same rules as creating a promotion, but the image is optional: without one, the current image stays.
 */
class UpdateLandingPromotionRequest extends StoreLandingPromotionRequest
{
    protected function imageRequirement(): string
    {
        return 'nullable';
    }
}
