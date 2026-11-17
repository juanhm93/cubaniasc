<?php

namespace App\Http\Controllers;

use App\Actions\ResolveVisibleLandingPromotion;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * Render the public landing with today's promotion, if any.
     */
    public function __invoke(ResolveVisibleLandingPromotion $resolveVisibleLandingPromotion): Response
    {
        return Inertia::render('welcome', [
            'promotion' => $resolveVisibleLandingPromotion->handle(),
        ]);
    }
}
